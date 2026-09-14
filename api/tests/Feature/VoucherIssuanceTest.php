<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Radius\RadiusAttribute;
use App\Domain\Voucher\BatchStatus;
use App\Domain\Voucher\VoucherCode;
use App\Domain\Voucher\VoucherCodeHasher;
use App\Domain\Voucher\VoucherIssuer;
use App\Domain\Voucher\VoucherStatus;
use App\Jobs\IssueVoucherBatch;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VoucherIssuanceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['code_prefix' => 'KAS']);
        $this->actingForTenant($this->tenant);

        $this->plan = Plan::factory()->for($this->tenant)->create([
            'rate_limit_down_kbps' => 4096,
            'rate_limit_up_kbps' => 1024,
            'device_limit' => 1,
            'shelf_life_days' => 90,
        ]);
    }

    #[Test]
    public function it_issues_the_requested_number_of_vouchers(): void
    {
        $vouchers = app(VoucherIssuer::class)->issue($this->plan, 25);

        $this->assertCount(25, $vouchers);
        $this->assertSame(25, Voucher::query()->count());
    }

    #[Test]
    public function every_issued_code_is_unique(): void
    {
        $vouchers = app(VoucherIssuer::class)->issue($this->plan, 200);

        $codes = $vouchers->map(fn (Voucher $voucher) => $voucher->code);

        $this->assertCount(200, $codes->unique());
    }

    #[Test]
    public function issued_codes_carry_the_tenant_prefix_and_a_valid_check_character(): void
    {
        $vouchers = app(VoucherIssuer::class)->issue($this->plan, 20);

        foreach ($vouchers as $voucher) {
            $this->assertStringStartsWith('KAS', $voucher->code);
            $this->assertTrue(
                VoucherCode::hasValidCheckCharacter($voucher->code),
                "Issued code {$voucher->code} has a bad check character.",
            );
        }
    }

    #[Test]
    public function it_snapshots_the_plan_terms_onto_each_voucher(): void
    {
        $voucher = app(VoucherIssuer::class)->issueOne($this->plan);

        $originalPrice = $this->plan->price_minor;

        $this->assertSame($this->plan->validity_seconds, $voucher->validity_seconds);
        $this->assertSame($originalPrice, $voucher->price_minor);
        $this->assertSame(4096, $voucher->rate_limit_down_kbps);

        /*
         * The point of the snapshot: raising the price afterwards must not change
         * what an already-printed card is worth.
         */
        $this->plan->update(['price_minor' => 999_999]);

        $this->assertSame($originalPrice, $voucher->fresh()->price_minor);
    }

    #[Test]
    public function it_sets_a_shelf_expiry_from_the_plans_shelf_life(): void
    {
        $voucher = app(VoucherIssuer::class)->issueOne($this->plan);

        $this->assertNotNull($voucher->shelf_expires_at);
        $this->assertSame(90, (int) round(now()->diffInDays($voucher->shelf_expires_at)));
    }

    #[Test]
    public function it_leaves_shelf_expiry_unset_when_the_plan_has_no_shelf_life(): void
    {
        $this->plan->update(['shelf_life_days' => null]);

        $voucher = app(VoucherIssuer::class)->issueOne($this->plan->fresh());

        $this->assertNull($voucher->shelf_expires_at);
    }

    #[Test]
    public function a_code_read_back_from_the_database_matches_what_was_issued(): void
    {
        $issued = app(VoucherIssuer::class)->issueOne($this->plan);

        /*
         * Bulk issuance encrypts by hand, since a raw insert never runs the
         * model's cast. Encrypting with the wrong helper still round-trips
         * through the object that wrote it and only breaks on reload, so the
         * check has to come from a fresh read.
         */
        $reloaded = Voucher::query()->findOrFail($issued->id);

        $this->assertSame($issued->code, $reloaded->code);
        $this->assertTrue(VoucherCode::hasValidCheckCharacter($reloaded->code));
    }

    #[Test]
    public function issued_codes_are_looked_up_by_hash_not_by_the_encrypted_column(): void
    {
        $voucher = app(VoucherIssuer::class)->issueOne($this->plan);
        $code = $voucher->code;

        $stored = DB::table('vouchers')->where('id', $voucher->id)->value('code');

        $this->assertNotSame($code, $stored, 'The code was stored in cleartext.');

        $found = Voucher::query()
            ->where('code_hash', app(VoucherCodeHasher::class)->hash($code))
            ->first();

        $this->assertNotNull($found);
        $this->assertSame($voucher->id, $found->id);
    }

    #[Test]
    public function issuing_authorises_the_code_in_radius(): void
    {
        $voucher = app(VoucherIssuer::class)->issueOne($this->plan);

        /*
         * A printed card has to work at the router with no involvement from this
         * application, so the RADIUS rows exist from the moment a voucher is
         * created rather than being written on redemption.
         */
        $this->assertDatabaseHas('radcheck', [
            'username' => $voucher->code,
            'attribute' => RadiusAttribute::CLEARTEXT_PASSWORD,
            'op' => ':=',
            'value' => $voucher->code,
        ]);

        $this->assertDatabaseHas('radreply', [
            'username' => $voucher->code,
            'attribute' => RadiusAttribute::MIKROTIK_RATE_LIMIT,
            // Upload first, which is the order RouterOS expects.
            'value' => '1024k/4096k',
        ]);
    }

    #[Test]
    public function a_single_device_bundle_omits_the_simultaneous_use_check(): void
    {
        $voucher = app(VoucherIssuer::class)->issueOne($this->plan);

        // Costs a radacct query on every authentication, and MAC binding already
        // restricts a one-device bundle, so it is left off.
        $this->assertDatabaseMissing('radcheck', [
            'username' => $voucher->code,
            'attribute' => RadiusAttribute::SIMULTANEOUS_USE,
        ]);
    }

    #[Test]
    public function a_multi_device_bundle_limits_concurrent_sessions(): void
    {
        $this->plan->update(['device_limit' => 3]);

        $voucher = app(VoucherIssuer::class)->issueOne($this->plan->fresh());

        $this->assertDatabaseHas('radcheck', [
            'username' => $voucher->code,
            'attribute' => RadiusAttribute::SIMULTANEOUS_USE,
            'value' => '3',
        ]);
    }

    #[Test]
    public function data_capped_bundles_report_usage_more_often(): void
    {
        $capped = Plan::factory()->for($this->tenant)->create([
            'data_cap_bytes' => 2_000_000_000,
        ]);

        $uncapped = Plan::factory()->for($this->tenant)->create([
            'data_cap_bytes' => null,
        ]);

        $cappedVoucher = app(VoucherIssuer::class)->issueOne($capped);
        $uncappedVoucher = app(VoucherIssuer::class)->issueOne($uncapped);

        /*
         * How far a client can overrun a cap is bounded by this interval, so a
         * metered bundle reports every two minutes and a time-only one every five.
         */
        $this->assertDatabaseHas('radreply', [
            'username' => $cappedVoucher->code,
            'attribute' => RadiusAttribute::ACCT_INTERIM_INTERVAL,
            'value' => '120',
        ]);

        $this->assertDatabaseHas('radreply', [
            'username' => $uncappedVoucher->code,
            'attribute' => RadiusAttribute::ACCT_INTERIM_INTERVAL,
            'value' => '300',
        ]);
    }

    #[Test]
    public function it_issues_a_large_batch_across_several_chunks(): void
    {
        // Forces the chunking path with a batch spanning three inserts.
        config()->set('kasi.voucher.insert_chunk', 40);

        $vouchers = app(VoucherIssuer::class)->issue($this->plan, 100);

        $this->assertCount(100, $vouchers);
        $this->assertCount(100, $vouchers->map(fn (Voucher $v) => $v->code)->unique());
        $this->assertSame(
            100,
            DB::table('radcheck')->where('attribute', RadiusAttribute::CLEARTEXT_PASSWORD)->count(),
        );
        $this->assertSame(100, DB::table('voucher_usage')->count());
    }

    #[Test]
    public function the_batch_job_issues_codes_and_marks_the_batch_ready(): void
    {
        $batch = VoucherBatch::factory()->for($this->tenant)->for($this->plan)->create([
            'quantity' => 30,
            'status' => BatchStatus::Generating,
        ]);

        (new IssueVoucherBatch($batch))->handle(app(VoucherIssuer::class));

        $this->assertSame(BatchStatus::Ready, $batch->fresh()->status);
        $this->assertSame(30, $batch->vouchers()->count());
    }

    #[Test]
    public function rerunning_the_batch_job_only_issues_the_shortfall(): void
    {
        $batch = VoucherBatch::factory()->for($this->tenant)->for($this->plan)->create([
            'quantity' => 20,
            'status' => BatchStatus::Generating,
        ]);

        app(VoucherIssuer::class)->issue($this->plan, 8, $batch);

        /*
         * A job that failed part-way and is retried must not issue the whole
         * quantity again, or the operator prints more cards than they sold.
         */
        (new IssueVoucherBatch($batch))->handle(app(VoucherIssuer::class));

        $this->assertSame(20, $batch->vouchers()->count());
    }

    #[Test]
    public function the_batch_job_does_nothing_for_a_batch_already_issued(): void
    {
        $batch = VoucherBatch::factory()->for($this->tenant)->for($this->plan)->create([
            'quantity' => 5,
            'status' => BatchStatus::Ready,
        ]);

        (new IssueVoucherBatch($batch))->handle(app(VoucherIssuer::class));

        $this->assertSame(0, $batch->vouchers()->count());
    }

    #[Test]
    public function issued_vouchers_start_unused(): void
    {
        $voucher = app(VoucherIssuer::class)->issueOne($this->plan);

        $this->assertSame(VoucherStatus::Unused, $voucher->status);
        $this->assertNull($voucher->first_used_at);
        $this->assertNull($voucher->expires_at);
        $this->assertNull($voucher->bound_mac);
    }

    #[Test]
    public function the_stored_suffix_matches_the_end_of_the_code(): void
    {
        $voucher = app(VoucherIssuer::class)->issueOne($this->plan);

        $this->assertSame(substr($voucher->code, -4), $voucher->code_suffix);
    }

    #[Test]
    public function it_refuses_to_issue_nothing(): void
    {
        $this->expectException(\RuntimeException::class);

        app(VoucherIssuer::class)->issue($this->plan, 0);
    }
}
