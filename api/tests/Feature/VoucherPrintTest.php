<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Voucher\BatchStatus;
use App\Domain\Voucher\VoucherCard;
use App\Domain\Voucher\VoucherIssuer;
use App\Domain\Voucher\VoucherStatus;
use App\Models\Plan;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VoucherPrintTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['code_prefix' => 'KAS']);
        $this->owner = User::factory()->for($this->tenant)->owner()->create();
        $this->plan = Plan::factory()->for($this->tenant)->create([
            'name' => 'Daily 24h',
            'data_cap_bytes' => 5_000_000_000,
            'rate_limit_down_kbps' => 4000,
            'shelf_life_days' => 60,
        ]);
    }

    #[Test]
    public function the_sheet_shows_every_code_with_its_bundle_and_a_qr_code(): void
    {
        $batch = $this->readyBatch(4);

        $response = $this->actingAs($this->owner)
            ->get("/api/print/voucher-batches/{$batch->id}")
            ->assertOk();

        foreach ($batch->vouchers as $voucher) {
            // Printed in dash-separated groups, which is how it is typed back in.
            $response->assertSee($voucher->displayCode());
        }

        $response->assertSee('Daily 24h')
            // Decimal GB, matching how the bundle was advertised.
            ->assertSee('5 GB')
            ->assertSee('4 Mbps')
            // Inline SVG rather than an image URL, so a card cannot print with a
            // hole where its code should be.
            ->assertSee('<svg', escape: false)
            ->assertDontSee('<?xml', escape: false);
    }

    #[Test]
    public function each_card_carries_the_shelf_expiry_so_stale_stock_is_obvious(): void
    {
        $batch = $this->readyBatch(1);

        $this->actingAs($this->owner)
            ->get("/api/print/voucher-batches/{$batch->id}")
            ->assertOk()
            ->assertSee('Use by '.now()->addDays(60)->format('j M Y'));
    }

    #[Test]
    public function the_card_shows_the_sites_ssid_when_the_batch_belongs_to_one(): void
    {
        $site = Site::factory()->for($this->tenant)->create(['ssid' => 'Kasi-Kariakoo']);
        $batch = $this->readyBatch(1);
        $batch->update(['site_id' => $site->id]);

        $this->actingAs($this->owner)
            ->get("/api/print/voucher-batches/{$batch->id}")
            ->assertOk()
            ->assertSee('Kasi-Kariakoo');
    }

    #[Test]
    public function a_sheet_is_capped_to_one_page_unless_a_range_is_asked_for(): void
    {
        config()->set('kasi.voucher.print_columns', 2);
        config()->set('kasi.voucher.print_rows', 3);

        $batch = $this->readyBatch(20);

        $response = $this->actingAs($this->owner)
            ->get("/api/print/voucher-batches/{$batch->id}")
            ->assertOk();

        /*
         * A ten-thousand-voucher batch rendered whole is hundreds of megabytes of
         * inline SVG and hangs the print dialog, so a sheet at a time is the
         * default rather than an option.
         */
        $this->assertSame(6, substr_count($response->getContent(), 'class="card"'));
    }

    #[Test]
    public function a_range_of_unprinted_codes_can_be_printed_after_a_jam_on_later_sheets(): void
    {
        config()->set('kasi.voucher.print_columns', 2);
        config()->set('kasi.voucher.print_rows', 3);

        $batch = $this->readyBatch(20);
        $codes = $batch->vouchers()->orderBy('id')->get();

        // First sheet (codes 1–6) is printed and locked.
        $this->actingAs($this->owner)
            ->get("/api/print/voucher-batches/{$batch->id}")
            ->assertOk();

        // A later range that was never printed can still be run off.
        $response = $this->actingAs($this->owner)
            ->get("/api/print/voucher-batches/{$batch->id}?from=1&to=3")
            ->assertOk();

        $response->assertSee($codes[6]->displayCode())
            ->assertSee($codes[8]->displayCode())
            ->assertDontSee($codes[0]->displayCode());
    }

    #[Test]
    public function the_same_codes_cannot_be_printed_twice(): void
    {
        config()->set('kasi.voucher.print_columns', 2);
        config()->set('kasi.voucher.print_rows', 3);

        $batch = $this->readyBatch(4);

        $this->actingAs($this->owner)
            ->get("/api/print/voucher-batches/{$batch->id}")
            ->assertOk();

        $this->actingAs($this->owner)
            ->getJson("/api/print/voucher-batches/{$batch->id}")
            ->assertJsonValidationErrors('batch');

        $this->assertSame(4, $batch->vouchers()->whereNotNull('printed_at')->count());
    }

    #[Test]
    public function printing_is_counted_and_audited_once_per_sheet(): void
    {
        config()->set('kasi.voucher.print_columns', 2);
        config()->set('kasi.voucher.print_rows', 2);

        $batch = $this->readyBatch(6);

        $this->actingAs($this->owner)->get("/api/print/voucher-batches/{$batch->id}")->assertOk();
        $this->actingAs($this->owner)->get("/api/print/voucher-batches/{$batch->id}")->assertOk();

        $this->assertSame(2, $batch->fresh()->print_count);
        $this->assertNotNull($batch->fresh()->printed_at);
        $this->assertSame(6, $batch->vouchers()->whereNotNull('printed_at')->count());

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->owner->id,
            'action' => 'voucher_batch.printed',
        ]);
    }

    #[Test]
    public function redeemed_codes_are_left_off_the_sheet(): void
    {
        $batch = $this->readyBatch(4);
        $redeemed = $batch->vouchers()->first();
        $redeemed->update(['status' => VoucherStatus::Active]);

        // A card printed for a code that no longer works gets sold, and the
        // customer comes back.
        $this->actingAs($this->owner)
            ->get("/api/print/voucher-batches/{$batch->id}")
            ->assertOk()
            ->assertDontSee($redeemed->displayCode());
    }

    #[Test]
    public function a_batch_with_nothing_left_to_print_says_so(): void
    {
        $batch = $this->readyBatch(2);

        $batch->vouchers->each(
            fn (Voucher $voucher) => $voucher->update(['status' => VoucherStatus::Active]),
        );

        $this->actingAs($this->owner)
            ->getJson("/api/print/voucher-batches/{$batch->id}")
            ->assertJsonValidationErrors('batch');
    }

    #[Test]
    public function a_batch_still_generating_cannot_be_printed(): void
    {
        $batch = VoucherBatch::factory()->for($this->tenant)->for($this->plan)->create([
            'quantity' => 10,
            'status' => BatchStatus::Generating,
        ]);

        // Its codes are incomplete, so the sheet would be short without saying so.
        $this->actingAs($this->owner)
            ->getJson("/api/print/voucher-batches/{$batch->id}")
            ->assertForbidden();
    }

    #[Test]
    public function a_withdrawn_batch_cannot_be_printed(): void
    {
        $batch = $this->readyBatch(2);
        $batch->update(['status' => BatchStatus::Disabled]);

        $this->actingAs($this->owner)
            ->getJson("/api/print/voucher-batches/{$batch->id}")
            ->assertForbidden();
    }

    #[Test]
    public function an_agent_can_print_the_batch_assigned_to_them(): void
    {
        $agent = User::factory()->for($this->tenant)->agent()->create();
        $batch = $this->readyBatch(3);
        $batch->update(['assigned_agent_id' => $agent->id]);

        // The reseller tier in full: an agent prints their own stock and can do
        // nothing else.
        $this->actingAs($agent)
            ->get("/api/print/voucher-batches/{$batch->id}")
            ->assertOk()
            ->assertSee($batch->vouchers->first()->displayCode());
    }

    #[Test]
    public function an_agent_cannot_print_another_agents_batch(): void
    {
        $agent = User::factory()->for($this->tenant)->agent()->create();
        $otherAgent = User::factory()->for($this->tenant)->agent()->create();

        $batch = $this->readyBatch(3);
        $batch->update(['assigned_agent_id' => $otherAgent->id]);

        $this->actingAs($agent)
            ->getJson("/api/print/voucher-batches/{$batch->id}")
            ->assertForbidden();
    }

    #[Test]
    public function another_operators_batch_cannot_be_printed(): void
    {
        $foreignTenant = Tenant::factory()->create();
        $foreignBatch = VoucherBatch::factory()
            ->for($foreignTenant)
            ->for(Plan::factory()->for($foreignTenant))
            ->create(['status' => BatchStatus::Ready]);

        $this->actingAs($this->owner)
            ->getJson("/api/print/voucher-batches/{$foreignBatch->id}")
            ->assertNotFound();
    }

    #[Test]
    public function the_qr_code_deep_links_into_the_portal_with_the_code(): void
    {
        config()->set('kasi.portal_url', 'https://portal.kasi.test');

        $batch = $this->readyBatch(1);
        $voucher = $batch->vouchers->first();

        $expected = 'https://portal.kasi.test/r/'.$voucher->code;

        /*
         * The link is inside the QR modules rather than the markup, so the payload
         * is checked where it is built. What matters here is that it is absolute
         * and carries the code: a relative link cannot be scanned from paper.
         */
        $this->assertSame($expected, VoucherCard::redemptionUrl($voucher->code));
        $this->assertSame(
            \App\Domain\Voucher\VoucherCode::normalise($voucher->code),
            VoucherCard::qrPayload($voucher->code),
        );
    }

    private function readyBatch(int $quantity): VoucherBatch
    {
        $batch = VoucherBatch::factory()->for($this->tenant)->for($this->plan)->create([
            'quantity' => $quantity,
            'status' => BatchStatus::Ready,
        ]);

        $this->actingForTenant($this->tenant);

        app(VoucherIssuer::class)->issue($this->plan, $quantity, $batch);

        return $batch;
    }
}
