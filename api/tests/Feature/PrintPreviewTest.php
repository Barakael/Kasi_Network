<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Voucher\BatchStatus;
use App\Domain\Voucher\VoucherIssuer;
use App\Models\Plan;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VoucherBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Writes a full sample sheet to storage/app/ so the card layout can be opened in
 * a browser's print preview.
 *
 * Excluded from the normal run. Whether a code is legible at 11.5pt and whether
 * the cut lines land between cards rather than through them are questions no
 * assertion answers; they need a person and a print dialog. This exists so that
 * person does not have to hand-build a batch first.
 *
 * Run with: php artisan test --group=preview
 */
#[Group('preview')]
class PrintPreviewTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_writes_a_sample_sheet(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Kasi Kariakoo',
            'code_prefix' => 'KAS',
        ]);

        $this->actingForTenant($tenant);

        $owner = User::factory()->for($tenant)->owner()->create();
        $site = Site::factory()->for($tenant)->create(['ssid' => 'Kasi-Kariakoo']);

        $plan = Plan::factory()->for($tenant)->create([
            'name' => 'Daily 5GB',
            'data_cap_bytes' => 5_000_000_000,
            'rate_limit_down_kbps' => 4000,
            'rate_limit_up_kbps' => 1000,
            'shelf_life_days' => 90,
        ]);

        $perSheet = (int) config('kasi.voucher.print_columns') * (int) config('kasi.voucher.print_rows');

        $batch = VoucherBatch::factory()->for($tenant)->for($plan)->create([
            'reference' => 'Kariakoo week 38',
            'site_id' => $site->id,
            'quantity' => $perSheet,
            'status' => BatchStatus::Ready,
        ]);

        app(VoucherIssuer::class)->issue($plan, $perSheet, $batch);

        $html = $this->actingAs($owner)
            ->get("/api/print/voucher-batches/{$batch->id}")
            ->assertOk()
            ->getContent();

        $path = storage_path('app/print-preview.html');
        file_put_contents($path, $html);

        fwrite(STDERR, "\nSample sheet written to {$path}\n");

        $this->assertFileExists($path);
    }
}
