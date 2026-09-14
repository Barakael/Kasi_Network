<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Voucher\BatchStatus;
use App\Domain\Voucher\VoucherIssuer;
use App\Models\VoucherBatch;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generates the codes for a batch in the background.
 *
 * Ten thousand vouchers is more work than an HTTP request should carry, so the
 * console creates the batch as `generating` and returns immediately; the operator
 * watches it flip to `ready`.
 *
 * Unique by batch, because a retry or a double-clicked button would otherwise
 * issue the quantity twice and the operator would print cards that outnumber
 * what they think they sold.
 */
class IssueVoucherBatch implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Generation is a handful of bulk inserts, so a failure is a real fault --
     * usually exhausted collision retries or a lost connection -- rather than
     * something a retry will fix. One retry covers a dropped connection.
     */
    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(public VoucherBatch $batch) {}

    public function uniqueId(): string
    {
        return (string) $this->batch->id;
    }

    public function handle(VoucherIssuer $issuer): void
    {
        $batch = $this->batch->fresh();

        if ($batch === null || $batch->status !== BatchStatus::Generating) {
            // Already issued, or the batch was deleted while queued.
            return;
        }

        /*
         * Counted rather than assumed: if an earlier attempt inserted some codes
         * before failing, issuing the full quantity again would overshoot. Only
         * the shortfall is issued.
         */
        $existing = $batch->vouchers()->count();
        $shortfall = $batch->quantity - $existing;

        if ($shortfall > 0) {
            $issuer->issue($batch->plan, $shortfall, $batch);
        }

        $batch->update(['status' => BatchStatus::Ready]);
    }

    public function failed(?Throwable $exception): void
    {
        /*
         * Left as `generating` deliberately. A batch that reports itself ready
         * with a short count would be printed and sold, whereas one stuck in
         * generating is visibly wrong in the console.
         */
        Log::error('Voucher batch issuance failed', [
            'batch_id' => $this->batch->id,
            'tenant_id' => $this->batch->tenant_id,
            'reason' => $exception?->getMessage(),
        ]);
    }
}
