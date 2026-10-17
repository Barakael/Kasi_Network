<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\UserRole;
use App\Models\NasDevice;
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

        app(CurrentTenant::class)->set($tenant);
        (new WaydaCommercialPlansSeeder)->seedFor($tenant);

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
}
