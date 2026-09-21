<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Tenancy\UserRole;
use App\Domain\Voucher\BillingPeriod;
use App\Domain\Voucher\QuotaAction;
use App\Models\NasDevice;
use App\Models\Plan;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A working operator to develop against: one site, one router, a full bundle
 * ladder and an account for each role.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'kasi-demo'],
            [
                'name' => 'Kasi Demo Network',
                'code_prefix' => 'KAS',
                'status' => 'active',
                'currency' => 'TZS',
                'timezone' => 'Africa/Dar_es_Salaam',
                'portal_name' => 'Kasi Demo WiFi',
                'primary_color' => '#2563eb',
                'support_phone' => '255700000000',
            ],
        );

        $this->seedUsers($tenant);
        $site = $this->seedSite($tenant);
        $this->seedRouter($tenant, $site);

        app(\App\Domain\Tenancy\CurrentTenant::class)->set($tenant);
        $this->seedPlans($tenant);

        $this->command?->info("Seeded tenant '{$tenant->name}' (prefix {$tenant->code_prefix}).");
        $this->command?->info('Sign in as owner@kasi.test / password.');
    }

    private function seedUsers(Tenant $tenant): void
    {
        $accounts = [
            ['owner@kasi.test', 'Amina Owner', UserRole::Owner],
            ['staff@kasi.test', 'Juma Staff', UserRole::Staff],
            ['agent@kasi.test', 'Neema Agent', UserRole::Agent],
        ];

        foreach ($accounts as [$email, $name, $role]) {
            User::firstOrCreate(
                ['email' => $email],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $name,
                    'role' => $role,
                    'password' => Hash::make('password'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );
        }

        User::firstOrCreate(
            ['email' => 'admin@kasi.test'],
            [
                // Platform administrators sit outside tenancy.
                'tenant_id' => null,
                'name' => 'Platform Admin',
                'role' => UserRole::PlatformAdmin,
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }

    private function seedSite(Tenant $tenant): Site
    {
        return Site::firstOrCreate(
            ['nas_identifier' => 'kasi-demo-site-1'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Kariakoo Branch',
                'slug' => 'kariakoo',
                'ssid' => 'Kasi-Demo',
                'timezone' => 'Africa/Dar_es_Salaam',
                'status' => 'active',
                'address' => 'Kariakoo, Dar es Salaam',
            ],
        );
    }

    private function seedRouter(Tenant $tenant, Site $site): void
    {
        NasDevice::firstOrCreate(
            ['nasname' => '192.168.88.1'],
            [
                'tenant_id' => $tenant->id,
                'site_id' => $site->id,
                'name' => 'hAP lite - Kariakoo',
                'shared_secret' => Str::random(32),
                'api_host' => '192.168.88.1',
                'api_port' => 8728,
                'api_username' => 'kasi-api',
                'api_password' => Str::random(24),
                'coa_port' => 3799,
                'status' => 'active',
            ],
        );
    }

    /**
     * The bundle ladder an operator would realistically sell, exercising each
     * kind of limit: pure wall clock, a data cap, a cumulative time budget, and
     * throttling instead of disconnection.
     */
    private function seedPlans(Tenant $tenant): void
    {
        $plans = [
            [
                'name' => 'Saa Moja',
                'description' => '1 hour, unlimited data',
                'billing_period' => BillingPeriod::Hourly,
                'validity_seconds' => 3600,
                'duration_seconds' => null,
                'data_cap_bytes' => null,
                'price_minor' => 500,
                'rate_limit_down_kbps' => 4096,
                'rate_limit_up_kbps' => 2048,
                'device_limit' => 1,
                'on_quota_exhausted' => QuotaAction::Disconnect,
                'sort_order' => 1,
            ],
            [
                'name' => 'Siku Moja',
                'description' => '24 hours, unlimited data',
                'billing_period' => BillingPeriod::Daily,
                'validity_seconds' => 86400,
                'duration_seconds' => null,
                'data_cap_bytes' => null,
                'price_minor' => 1500,
                'rate_limit_down_kbps' => 5120,
                'rate_limit_up_kbps' => 2048,
                'device_limit' => 1,
                'on_quota_exhausted' => QuotaAction::Disconnect,
                'sort_order' => 2,
            ],
            [
                'name' => 'Wiki Moja',
                'description' => '7 days, 10 GB then slower',
                'billing_period' => BillingPeriod::Weekly,
                'validity_seconds' => 604800,
                'duration_seconds' => null,
                'data_cap_bytes' => 10 * 1024 ** 3,
                'price_minor' => 7000,
                'rate_limit_down_kbps' => 8192,
                'rate_limit_up_kbps' => 4096,
                'device_limit' => 2,
                'on_quota_exhausted' => QuotaAction::Throttle,
                'throttle_down_kbps' => 512,
                'throttle_up_kbps' => 256,
                'sort_order' => 3,
            ],
            [
                'name' => 'Mwezi Mmoja',
                'description' => '30 days, 50 GB, up to 3 devices',
                'billing_period' => BillingPeriod::Monthly,
                'validity_seconds' => 2592000,
                'duration_seconds' => null,
                'data_cap_bytes' => 50 * 1024 ** 3,
                'price_minor' => 25000,
                'rate_limit_down_kbps' => 10240,
                'rate_limit_up_kbps' => 5120,
                'device_limit' => 3,
                'on_quota_exhausted' => QuotaAction::Throttle,
                'throttle_down_kbps' => 1024,
                'throttle_up_kbps' => 512,
                'sort_order' => 4,
            ],
            [
                'name' => 'Masaa 10',
                'description' => '10 hours of use, any time within 30 days',
                'billing_period' => BillingPeriod::Custom,
                'validity_seconds' => 2592000,
                // The distinction the two limits exist for: a month to use it in,
                // but only ten hours of actual connection time.
                'duration_seconds' => 36000,
                'data_cap_bytes' => null,
                'price_minor' => 6000,
                'rate_limit_down_kbps' => 5120,
                'rate_limit_up_kbps' => 2048,
                'device_limit' => 1,
                'on_quota_exhausted' => QuotaAction::Disconnect,
                'sort_order' => 5,
            ],
        ];

        foreach ($plans as $attributes) {
            Plan::withTrashed()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $attributes['name']],
                [
                    ...$attributes,
                    'shelf_life_days' => 365,
                    'is_active' => true,
                    'is_sold_online' => true,
                    'deleted_at' => null,
                ],
            );
        }
    }
}
