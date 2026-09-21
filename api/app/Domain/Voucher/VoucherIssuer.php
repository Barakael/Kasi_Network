<?php

declare(strict_types=1);

namespace App\Domain\Voucher;

use App\Domain\Radius\RadiusProvisioner;
use App\Models\Plan;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Creates vouchers and makes them redeemable.
 *
 * An operator printing cards for a week of trading asks for ten thousand at once,
 * so codes are generated and written in chunks rather than one model at a time.
 * A ten-thousand-row batch is a few seconds of work this way and several minutes
 * with per-row inserts.
 *
 * Two properties matter more than speed:
 *
 *   - Every code must be unique across the whole platform, since radcheck keys on
 *     username alone and has nowhere to record which operator a code belongs to.
 *   - A voucher must be authorised in RADIUS before anyone can be told it exists,
 *     otherwise a card gets printed and handed over while the code is refused.
 *
 * Both are handled by inserting the vouchers and their RADIUS rows in one
 * transaction, relying on the unique index rather than a pre-flight check for
 * uniqueness, and retrying the chunk on collision.
 */
final readonly class VoucherIssuer
{
    public function __construct(
        private VoucherCodeHasher $hasher,
        private RadiusProvisioner $provisioner,
    ) {}

    /**
     * Issues vouchers for a batch, returning them with their cleartext codes.
     *
     * @return Collection<int, Voucher>
     */
    public function issueForBatch(VoucherBatch $batch): Collection
    {
        return $this->issue($batch->plan, $batch->quantity, $batch);
    }

    /**
     * Issues one voucher, used when an online order is paid.
     */
    public function issueOne(Plan $plan): Voucher
    {
        return $this->issue($plan, 1)->firstOrFail();
    }

    /**
     * @return Collection<int, Voucher>
     */
    public function issue(Plan $plan, int $quantity, ?VoucherBatch $batch = null): Collection
    {
        if ($quantity < 1) {
            throw new RuntimeException('A voucher batch needs at least one voucher.');
        }

        $chunkSize = (int) config('kasi.voucher.insert_chunk');
        $issued = collect();

        foreach ($this->chunkSizes($quantity, $chunkSize) as $size) {
            $issued = $issued->concat($this->issueChunk($plan, $size, $batch));
        }

        return $issued;
    }

    /**
     * Inserts one chunk, retrying if a generated code already exists.
     *
     * The chance of a collision is negligible -- ten characters over a 31-symbol
     * alphabet is about 8.2e14 codes -- but "negligible" over millions of issued
     * vouchers is not "never", and the failure mode without a retry is a batch
     * that dies part-way through. Retrying regenerates the whole chunk, which is
     * wasteful and completely fine at these odds.
     *
     * @return Collection<int, Voucher>
     */
    private function issueChunk(Plan $plan, int $size, ?VoucherBatch $batch): Collection
    {
        $attempts = (int) config('kasi.voucher.collision_retries');

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return DB::transaction(fn (): Collection => $this->insertChunk($plan, $size, $batch));
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt === $attempts) {
                    throw new RuntimeException(
                        "Could not generate {$size} unique voucher codes after {$attempts} attempts.",
                        previous: $e,
                    );
                }
            }
        }

        throw new RuntimeException('Unreachable: voucher chunk issue loop exited without result.');
    }

    /**
     * @return Collection<int, Voucher>
     */
    private function insertChunk(Plan $plan, int $size, ?VoucherBatch $batch): Collection
    {
        $bodyLength = (int) config('kasi.voucher.body_length');
        $suffixLength = (int) config('kasi.voucher.group_size');
        $now = Carbon::now();

        $shelfExpiresAt = $plan->shelf_life_days === null
            ? null
            : $now->copy()->addDays($plan->shelf_life_days);

        $terms = $plan->termsSnapshot();
        // No shared prefix on the card: every code is a fresh random string.
        // Tenant ownership is tenant_id + global code_hash uniqueness.
        $codes = $this->uniqueCodes($bodyLength, $size);
        $rows = [];

        foreach ($codes as $code) {
            $rows[] = [
                ...$terms,
                'tenant_id' => $plan->tenant_id,
                'plan_id' => $plan->id,
                'batch_id' => $batch?->id,
                /*
                 * Encrypted by hand because this is a raw insert rather than a
                 * saved model, so the cast on Voucher::$code never runs.
                 *
                 * encryptString, not encrypt: the latter serialises its input,
                 * and the encrypted cast reads with decryptString, which does
                 * not unserialise. Mixing them stores codes that come back out
                 * as 's:14:"KAS...";' and authenticate against nothing.
                 */
                'code' => Crypt::encryptString($code),
                'code_hash' => $this->hasher->hash($code),
                'code_suffix' => VoucherCode::suffix($code, $suffixLength),
                'status' => VoucherStatus::Unused->value,
                'shelf_expires_at' => $shelfExpiresAt,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        Voucher::insert($rows);

        /*
         * Read back to pick up the assigned ids, matching on the hashes just
         * inserted rather than on a timestamp, which would also catch vouchers
         * from a concurrent batch. Scoped to the plan's operator explicitly
         * because issuance also runs from queued jobs, where no tenant has been
         * resolved from a request.
         */
        $vouchers = Voucher::withoutTenantScope()
            ->where('tenant_id', $plan->tenant_id)
            ->whereIn('code_hash', array_column($rows, 'code_hash'))
            ->get();

        /*
         * The cleartext is needed for the radcheck rows and for printing, and the
         * encrypted cast produces a fresh ciphertext each time, so it cannot be
         * matched back by column. Codes are re-attached by hash instead.
         */
        $byHash = [];

        foreach ($codes as $code) {
            $byHash[$this->hasher->hash($code)] = $code;
        }

        foreach ($vouchers as $voucher) {
            $voucher->code = $byHash[$voucher->code_hash];
            // Not a pending change: the same value is already stored, under a
            // different ciphertext.
            $voucher->syncOriginal();
        }

        $this->provisioner->provisionMany($vouchers);
        $this->seedUsageRows($vouchers);

        return $vouchers;
    }

    /**
     * Inserts empty rollup rows so FreeRADIUS sqlcounter can look a voucher up
     * by User-Name on the first authentication, before any accounting exists.
     *
     * @param  Collection<int, Voucher>  $vouchers
     */
    private function seedUsageRows(Collection $vouchers): void
    {
        $now = Carbon::now();
        $rows = [];

        foreach ($vouchers as $voucher) {
            $rows[] = [
                'tenant_id' => $voucher->tenant_id,
                'voucher_id' => $voucher->id,
                'username' => $voucher->code,
                'seconds_used' => 0,
                'bytes_in' => 0,
                'bytes_out' => 0,
                'session_count' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('voucher_usage')->insert($rows);
    }

    /**
     * Generates the requested number of distinct codes.
     *
     * Deduplicating in memory first means a repeat inside one chunk does not cost
     * a database round trip to discover; the unique index remains the authority
     * for collisions against codes already issued.
     *
     * @return array<int, string>
     */
    private function uniqueCodes(int $bodyLength, int $size): array
    {
        $codes = [];

        while (count($codes) < $size) {
            $code = VoucherCode::generate($bodyLength);
            $codes[$code] = true;
        }

        return array_keys($codes);
    }

    /**
     * Splits a quantity into chunk-sized pieces.
     *
     * @return array<int, int>
     */
    private function chunkSizes(int $quantity, int $chunkSize): array
    {
        $sizes = array_fill(0, intdiv($quantity, $chunkSize), $chunkSize);
        $remainder = $quantity % $chunkSize;

        if ($remainder > 0) {
            $sizes[] = $remainder;
        }

        return $sizes;
    }
}
