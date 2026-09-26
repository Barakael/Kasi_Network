<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Voucher\BatchStatus;
use App\Domain\Voucher\VoucherIssuer;
use App\Domain\Voucher\VoucherStatus;
use App\Jobs\IssueVoucherBatch;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VoucherBatchTest extends TestCase
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
        $this->plan = Plan::factory()->for($this->tenant)->create();
    }

    #[Test]
    public function creating_a_batch_queues_the_code_generation(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->owner)->postJson('/api/v1/voucher-batches', [
            'plan_id' => $this->plan->id,
            'quantity' => 500,
            'reference' => 'Kariakoo week 38',
        ]);

        // 202, not 201: the batch exists but its codes are still being written.
        $response->assertAccepted()
            ->assertJsonPath('data.status', BatchStatus::Generating->value)
            ->assertJsonPath('data.quantity', 500);

        Queue::assertPushed(IssueVoucherBatch::class);
    }

    #[Test]
    public function a_batch_without_a_reference_gets_one_generated(): void
    {
        Queue::fake();

        $this->actingAs($this->owner)
            ->postJson('/api/v1/voucher-batches', [
                'plan_id' => $this->plan->id,
                'quantity' => 10,
            ])
            ->assertAccepted()
            ->assertJsonPath('data.reference', fn (?string $reference) => is_string($reference) && $reference !== '');
    }

    #[Test]
    public function a_batch_cannot_be_created_against_another_operators_plan(): void
    {
        $foreign = Plan::factory()->for(Tenant::factory())->create();

        $this->actingAs($this->owner)
            ->postJson('/api/v1/voucher-batches', [
                'plan_id' => $foreign->id,
                'quantity' => 10,
            ])
            ->assertJsonValidationErrors('plan_id');
    }

    #[Test]
    public function a_batch_cannot_be_assigned_to_another_operators_agent(): void
    {
        $foreignAgent = User::factory()->for(Tenant::factory())->agent()->create();

        /*
         * Users are not tenant-scoped globally, since login has to find an account
         * before any tenant is known, so this is the one place the boundary is
         * enforced by hand rather than by the global scope.
         */
        $this->actingAs($this->owner)
            ->postJson('/api/v1/voucher-batches', [
                'plan_id' => $this->plan->id,
                'quantity' => 10,
                'assigned_agent_id' => $foreignAgent->id,
            ])
            ->assertJsonValidationErrors('assigned_agent_id');
    }

    #[Test]
    public function a_staff_member_cannot_be_assigned_stock_as_though_they_were_an_agent(): void
    {
        $staff = User::factory()->for($this->tenant)->staff()->create();

        $this->actingAs($this->owner)
            ->postJson('/api/v1/voucher-batches', [
                'plan_id' => $this->plan->id,
                'quantity' => 10,
                'assigned_agent_id' => $staff->id,
            ])
            ->assertJsonValidationErrors('assigned_agent_id');
    }

    #[Test]
    public function a_batch_larger_than_the_configured_ceiling_is_refused(): void
    {
        config()->set('kasi.voucher.max_batch_quantity', 100);

        $this->actingAs($this->owner)
            ->postJson('/api/v1/voucher-batches', [
                'plan_id' => $this->plan->id,
                'quantity' => 101,
            ])
            ->assertJsonValidationErrors('quantity');
    }

    #[Test]
    public function an_agent_cannot_issue_stock_for_themselves(): void
    {
        $agent = User::factory()->for($this->tenant)->agent()->create();

        // The line that defines the reseller tier: agents distribute, they do not
        // mint.
        $this->actingAs($agent)
            ->postJson('/api/v1/voucher-batches', [
                'plan_id' => $this->plan->id,
                'quantity' => 10,
            ])
            ->assertForbidden();
    }

    #[Test]
    public function an_agent_only_sees_batches_assigned_to_them(): void
    {
        $agent = User::factory()->for($this->tenant)->agent()->create();
        $otherAgent = User::factory()->for($this->tenant)->agent()->create();

        VoucherBatch::factory()->for($this->tenant)->for($this->plan)->count(2)->create([
            'assigned_agent_id' => $agent->id,
            'status' => BatchStatus::Ready,
        ]);

        VoucherBatch::factory()->for($this->tenant)->for($this->plan)->create([
            'assigned_agent_id' => $otherAgent->id,
            'status' => BatchStatus::Ready,
        ]);

        $this->actingAs($agent)
            ->getJson('/api/v1/voucher-batches')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function an_agent_cannot_open_another_agents_batch(): void
    {
        $agent = User::factory()->for($this->tenant)->agent()->create();
        $otherAgent = User::factory()->for($this->tenant)->agent()->create();

        $batch = VoucherBatch::factory()->for($this->tenant)->for($this->plan)->create([
            'assigned_agent_id' => $otherAgent->id,
            'status' => BatchStatus::Ready,
        ]);

        $this->actingAs($agent)
            ->getJson("/api/v1/voucher-batches/{$batch->id}")
            ->assertForbidden();
    }

    #[Test]
    public function a_batch_listing_reports_how_much_has_been_sold_through(): void
    {
        $batch = $this->readyBatch(10);

        $batch->vouchers()->limit(4)->get()
            ->each(fn (Voucher $voucher) => $voucher->update(['status' => VoucherStatus::Active]));

        $this->actingAs($this->owner)
            ->getJson("/api/v1/voucher-batches/{$batch->id}")
            ->assertOk()
            ->assertJsonPath('data.issued_count', 10)
            ->assertJsonPath('data.redeemed_count', 4)
            ->assertJsonPath('data.unused_count', 6)
            ->assertJsonPath('data.printable_count', 6)
            ->assertJsonPath('data.is_printable', true);
    }

    #[Test]
    public function a_voucher_listing_never_includes_the_codes(): void
    {
        $batch = $this->readyBatch(3);

        $response = $this->actingAs($this->owner)
            ->getJson("/api/v1/voucher-batches/{$batch->id}/vouchers")
            ->assertOk()
            ->assertJsonCount(3, 'data');

        /*
         * A code is a live RADIUS credential. Listing vouchers is routine, so the
         * codes are deliberately absent and only the print layout and the audited
         * reveal endpoint return them.
         */
        foreach ($batch->vouchers as $voucher) {
            $response->assertDontSee($voucher->code);
        }

        $response->assertJsonMissingPath('data.0.code');
    }

    #[Test]
    public function withdrawing_a_batch_stops_its_unused_codes_working_at_the_router(): void
    {
        $batch = $this->readyBatch(6);

        $redeemed = $batch->vouchers()->first();
        $redeemed->update(['status' => VoucherStatus::Active]);

        $this->actingAs($this->owner)
            ->postJson("/api/v1/voucher-batches/{$batch->id}/disable", ['reason' => 'Stock reported stolen'])
            ->assertOk()
            ->assertJsonPath('vouchers_disabled', 5);

        $this->assertSame(BatchStatus::Disabled, $batch->fresh()->status);

        // The unused codes no longer authenticate.
        $this->assertSame(1, DB::table('radcheck')->count());

        /*
         * The already-redeemed voucher is untouched. Its holder paid, and cutting
         * them off over someone else's theft punishes the wrong person.
         */
        $this->assertDatabaseHas('radcheck', ['username' => $redeemed->code]);
        $this->assertSame(VoucherStatus::Active, $redeemed->fresh()->status);
    }

    #[Test]
    public function withdrawing_a_batch_records_who_did_it_and_why(): void
    {
        $batch = $this->readyBatch(2);

        $this->actingAs($this->owner)
            ->postJson("/api/v1/voucher-batches/{$batch->id}/disable", ['reason' => 'Printer jam, reprinted'])
            ->assertOk();

        $voucher = $batch->vouchers()->first();

        $this->assertSame($this->owner->id, $voucher->disabled_by_user_id);
        $this->assertSame('Printer jam, reprinted', $voucher->disable_reason);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->owner->id,
            'action' => 'voucher_batch.disabled',
        ]);
    }

    #[Test]
    public function an_agent_cannot_withdraw_a_batch_even_their_own(): void
    {
        $agent = User::factory()->for($this->tenant)->agent()->create();
        $batch = $this->readyBatch(2);
        $batch->update(['assigned_agent_id' => $agent->id]);

        $this->actingAs($agent)
            ->postJson("/api/v1/voucher-batches/{$batch->id}/disable")
            ->assertForbidden();
    }

    /**
     * A batch with its codes already issued.
     */
    private function readyBatch(int $quantity): VoucherBatch
    {
        $batch = VoucherBatch::factory()->for($this->tenant)->for($this->plan)->create([
            'quantity' => $quantity,
            'status' => BatchStatus::Generating,
        ]);

        $this->actingForTenant($this->tenant);

        app(VoucherIssuer::class)->issue($this->plan, $quantity, $batch);
        $batch->update(['status' => BatchStatus::Ready]);

        return $batch;
    }
}
