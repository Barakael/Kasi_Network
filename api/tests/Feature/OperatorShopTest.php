<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Auth\Totp;
use App\Domain\Billing\OrderStatus;
use App\Domain\Tenancy\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OperatorShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_system_admin_can_set_portal_name_phone_and_logo(): void
    {
        Storage::fake('public');

        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->owner()->create();

        $this->actingAs($owner)
            ->patchJson('/api/v1/settings', [
                'portal_name' => 'Wayda Hotspot',
                'support_phone' => '0712345678',
            ])
            ->assertOk()
            ->assertJsonPath('data.portal_name', 'Wayda Hotspot')
            ->assertJsonMissingPath('data.palmpesa_api_token');

        $file = UploadedFile::fake()->image('logo.png', 80, 80);

        $this->actingAs($owner)
            ->post('/api/v1/settings/logo', ['logo' => $file])
            ->assertOk()
            ->assertJsonPath('data.logo_url', fn ($url) => is_string($url) && str_contains($url, '/logo'));

        $this->get('/api/portal/tenants/'.$tenant->uuid.'/logo')->assertOk();
    }

    public function test_super_admin_invites_a_system_administrator(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/platform/tenants', [
                'name' => 'Kariakoo WiFi',
                'admin_name' => 'Amina',
                'admin_email' => 'amina@wayda.test',
            ])
            ->assertCreated();

        $this->assertNotEmpty($response->json('password'));
        $this->assertDatabaseHas('users', [
            'email' => 'amina@wayda.test',
            'role' => UserRole::Owner->value,
        ]);
    }

    public function test_an_agent_is_tied_to_a_site(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->owner()->create();
        $site = Site::factory()->for($tenant)->create();

        $this->actingAs($owner)
            ->postJson('/api/v1/agents', [
                'name' => 'Neema',
                'email' => 'neema@wayda.test',
                'site_ids' => [$site->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'neema@wayda.test');

        $agent = User::query()->where('email', 'neema@wayda.test')->first();
        $this->assertTrue($agent->sites()->where('sites.id', $site->id)->exists());
    }

    public function test_portal_identify_creates_a_customer_and_unlocks_packages(): void
    {
        $tenant = Tenant::factory()->create();
        $site = Site::factory()->for($tenant)->create(['nas_identifier' => 'site-abc']);
        Plan::factory()->for($tenant)->create();

        $token = $this->postJson('/api/portal/bootstrap', ['site' => 'site-abc'])->json('token');

        $this->withHeader('X-Kasi-Portal-Token', $token)
            ->postJson('/api/portal/identify', ['phone' => '0629288966'])
            ->assertOk()
            ->assertJsonPath('needs_phone', false)
            ->assertJsonCount(1, 'plans');

        $this->assertDatabaseHas('customers', [
            'tenant_id' => $tenant->id,
            'phone' => '255629288966',
            'site_id' => $site->id,
        ]);
    }

    public function test_system_admin_sees_customers_as_kimya_until_they_have_access(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->owner()->create();
        $site = Site::factory()->for($tenant)->create();
        Customer::factory()->for($tenant)->atSite($site)->create(['phone' => '255712345678']);

        $this->actingAs($owner)
            ->getJson('/api/v1/customers?status=kimya')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'kimya')
            ->assertJsonPath('data.0.phone', '***5678')
            ->assertJsonPath('data.0.last_package', null)
            ->assertJsonPath('data.0.paid_via', null);
    }

    public function test_a_site_opens_with_router_online_today_and_remaining_cards(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->owner()->create();
        $site = Site::factory()->for($tenant)->create();

        $this->actingAs($owner)
            ->getJson('/api/v1/sites/'.$site->id)
            ->assertOk()
            ->assertJsonPath('data.name', $site->name)
            ->assertJsonPath('remaining_cards', 0)
            ->assertJsonPath('online_count', 0)
            ->assertJsonPath('today.total_minor', 0);
    }

    public function test_wateja_show_last_package_and_kadi_channel(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->owner()->create();
        $this->actingForTenant($tenant);
        $site = Site::factory()->for($tenant)->create();
        $plan = Plan::factory()->for($tenant)->create(['name' => 'Wiki Moja']);
        $customer = Customer::factory()->for($tenant)->atSite($site)->create();
        $batch = VoucherBatch::factory()->for($tenant)->for($plan)->create(['site_id' => $site->id]);
        Voucher::factory()->forPlan($plan)->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'batch_id' => $batch->id,
        ]);

        $this->actingAs($owner)
            ->getJson('/api/v1/customers/'.$customer->id)
            ->assertOk()
            ->assertJsonPath('data.last_package', 'Wiki Moja')
            ->assertJsonPath('data.paid_via', 'kadi');
    }

    public function test_collections_split_lipia_and_kadi(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->owner()->create();
        $this->actingForTenant($tenant);
        $plan = Plan::factory()->for($tenant)->create(['price_minor' => 1000]);

        Order::factory()->for($plan)->create([
            'tenant_id' => $tenant->id,
            'status' => OrderStatus::Fulfilled,
            'amount_minor' => 1000,
            'paid_at' => now(),
        ]);

        Voucher::factory()->forPlan($plan)->create([
            'tenant_id' => $tenant->id,
            'price_minor' => 500,
            'first_used_at' => now(),
        ]);

        $this->actingAs($owner)
            ->getJson('/api/v1/reports/collections?period=day')
            ->assertOk()
            ->assertJsonPath('lipia_minor', 1000)
            ->assertJsonPath('kadi_minor', 500)
            ->assertJsonPath('total_minor', 1500);
    }

    public function test_agent_desk_is_scoped_to_assigned_site(): void
    {
        $tenant = Tenant::factory()->create();
        $site = Site::factory()->for($tenant)->create(['name' => 'Kariakoo']);
        $agent = User::factory()->for($tenant)->agent()->create();
        $agent->sites()->attach($site->id);

        $this->actingAs($agent)
            ->getJson('/api/v1/agent/desk')
            ->assertOk()
            ->assertJsonPath('site.name', 'Kariakoo')
            ->assertJsonPath('remaining_cards', 0);
    }

    public function test_an_offer_changes_the_live_price_and_can_be_restored(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->owner()->create();
        $plan = Plan::factory()->for($tenant)->create(['price_minor' => 5000, 'name' => 'Wiki Moja']);

        $this->actingAs($owner)
            ->postJson('/api/v1/plans/'.$plan->id.'/offer', [
                'price_minor' => 4000,
                'offer_label' => 'Wiki Moja 4000 hadi Jumatatu',
                'offer_ends_at' => now()->addDays(3)->toIso8601String(),
            ])
            ->assertOk()
            ->assertJsonPath('data.price_minor', 4000)
            ->assertJsonPath('data.has_active_offer', true);

        $this->actingAs($owner)
            ->postJson('/api/v1/plans/'.$plan->id.'/restore-price')
            ->assertOk()
            ->assertJsonPath('data.price_minor', 5000)
            ->assertJsonPath('data.has_active_offer', false);
    }

    public function test_two_factor_blocks_login_until_the_code_is_given(): void
    {
        $tenant = Tenant::factory()->create();
        $secret = Totp::secret();
        User::factory()->for($tenant)->owner()->create([
            'email' => 'owner@kasi.test',
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@kasi.test',
            'password' => 'password',
            'device_name' => 'Test',
        ])
            ->assertForbidden()
            ->assertJsonPath('requires_two_factor', true);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@kasi.test',
            'password' => 'password',
            'device_name' => 'Test',
            'two_factor_code' => Totp::code($secret),
        ])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_super_admin_can_bill_a_system_administrator(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/platform/invoices', [
                'tenant_uuid' => $tenant->uuid,
                'amount_minor' => 25000,
                'period_label' => 'September 2026',
            ])
            ->assertCreated()
            ->assertJsonPath('data.amount_minor', 25000);

        $this->assertDatabaseHas('platform_invoices', [
            'tenant_id' => $tenant->id,
            'amount_minor' => 25000,
        ]);
    }

    public function test_a_campaign_is_returned_on_the_portal_after_identify(): void
    {
        $tenant = Tenant::factory()->create();
        Site::factory()->for($tenant)->create(['nas_identifier' => 'site-abc']);
        $owner = User::factory()->for($tenant)->owner()->create();

        $this->actingAs($owner)
            ->postJson('/api/v1/campaigns', [
                'title' => 'Wiki Moja 4000',
                'body' => 'Bei maalum hadi Jumatatu.',
                'audience' => 'all',
            ])
            ->assertCreated();

        $this->postJson('/api/portal/bootstrap', [
            'site' => 'site-abc',
            'phone' => '0712345678',
        ])
            ->assertOk()
            ->assertJsonPath('campaign.title', 'Wiki Moja 4000');
    }
}
