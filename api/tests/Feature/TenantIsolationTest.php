<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Tenancy\CurrentTenant;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_queries_only_return_records_for_the_current_tenant(): void
    {
        $first = Tenant::factory()->create();
        $second = Tenant::factory()->create();

        Plan::factory()->for($first)->count(2)->create();
        Plan::factory()->for($second)->count(3)->create();

        $this->actingForTenant($first);
        $this->assertCount(2, Plan::all());

        $this->actingForTenant($second);
        $this->assertCount(3, Plan::all());
    }

    public function test_a_record_belonging_to_another_tenant_cannot_be_found_by_id(): void
    {
        $owner = Tenant::factory()->create();
        $other = Tenant::factory()->create();

        $plan = Plan::factory()->for($other)->create();

        $this->actingForTenant($owner);

        // The decisive case: even holding a valid id from another operator, the
        // scope prevents reading it. Without this, any endpoint taking an id in
        // the URL would be a cross-tenant read.
        $this->assertNull(Plan::find($plan->id));
    }

    public function test_tenant_id_is_stamped_automatically_on_create(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingForTenant($tenant);

        $plan = Plan::factory()->create(['tenant_id' => null]);

        $this->assertSame($tenant->id, $plan->fresh()->tenant_id);
    }

    public function test_background_work_can_opt_out_of_scoping(): void
    {
        $first = Tenant::factory()->create();
        $second = Tenant::factory()->create();

        Plan::factory()->for($first)->create();
        Plan::factory()->for($second)->create();

        $this->actingForTenant($first);

        // The accounting rollup and quota enforcement sweep every operator at
        // once, so they need a deliberate way out of the scope.
        $all = app(CurrentTenant::class)->withoutTenant(fn () => Plan::all());

        $this->assertCount(2, $all);
    }

    public function test_scoping_is_restored_after_running_unscoped(): void
    {
        $tenant = Tenant::factory()->create();
        Plan::factory()->for(Tenant::factory()->create())->create();
        Plan::factory()->for($tenant)->create();

        $this->actingForTenant($tenant);

        app(CurrentTenant::class)->withoutTenant(fn () => Plan::all());

        $this->assertCount(1, Plan::all());
    }

    public function test_no_scope_is_applied_when_no_tenant_is_resolved(): void
    {
        Plan::factory()->for(Tenant::factory()->create())->create();
        Plan::factory()->for(Tenant::factory()->create())->create();

        // Console commands run without a tenant context and must still see
        // everything rather than silently returning nothing.
        $this->assertCount(2, Plan::all());
    }

    public function test_users_are_not_tenant_scoped_so_authentication_can_work(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();

        $user = User::factory()->for($otherTenant)->create(['email' => 'owner@example.test']);

        $this->actingForTenant($tenant);

        /*
         * Login has to find a user by email before any tenant is known. A global
         * scope on User would make every login fail, so User deliberately opts
         * out and filters explicitly instead.
         */
        $found = User::where('email', 'owner@example.test')->first();

        $this->assertNotNull($found);
        $this->assertSame($user->id, $found->id);
    }

    public function test_voucher_codes_are_encrypted_at_rest(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingForTenant($tenant);

        $voucher = Voucher::factory()->for(Plan::factory()->for($tenant))->create();
        $plaintext = $voucher->code;

        $stored = (string) $this->getConnection()
            ->table('vouchers')
            ->where('id', $voucher->id)
            ->value('code');

        $this->assertNotSame($plaintext, $stored);
        $this->assertStringNotContainsString($plaintext, $stored);

        // Still readable through the model, so printing and provisioning work.
        $this->assertSame($plaintext, $voucher->fresh()->code);
    }

    public function test_a_voucher_can_be_found_by_its_code_hash(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingForTenant($tenant);

        $voucher = Voucher::factory()->for(Plan::factory()->for($tenant))->create();

        // Redemption cannot search the encrypted column, so it looks the voucher
        // up by the keyed hash instead.
        $found = Voucher::where('code_hash', $voucher->code_hash)->first();

        $this->assertNotNull($found);
        $this->assertSame($voucher->id, $found->id);
    }
}
