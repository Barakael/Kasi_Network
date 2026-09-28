<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Radius\NasSynchroniser;
use App\Models\Nas;
use App\Models\NasDevice;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NasDeviceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_snippet_points_radius_at_the_public_host_and_redirects_to_the_portal(): void
    {
        config()->set('kasi.radius.public_host', '161.97.182.204');
        config()->set('kasi.portal_url', 'https://net.wayda.co.tz');
        config()->set('app.url', 'https://net.wayda.co.tz');

        [$owner, $nas] = $this->router('102.202.74.55', apiHost: '192.168.10.212');

        $response = $this->actingAs($owner)
            ->getJson("/api/v1/nas-devices/{$nas->id}/snippet")
            ->assertOk();

        $rsc = $response->json('rsc');
        $login = $response->json('login_html');

        $this->assertStringContainsString('address=161.97.182.204', $rsc);
        $this->assertStringNotContainsString('src-address=', $rsc);
        $this->assertStringContainsString('dst-host=net.wayda.co.tz', $rsc);
        $this->assertStringNotContainsString('comment="Kasi API"', $rsc);
        $this->assertStringContainsString('dns-server=192.168.10.212', $rsc);

        $this->assertStringContainsString('login-by=http-pap,http-chap,cookie,mac-cookie', $rsc);
        $this->assertStringContainsString('https://net.wayda.co.tz/?', $login);
        $this->assertStringContainsString('site: "'.$nas->site->nas_identifier.'"', $login);
        $this->assertStringContainsString('view: "packages"', $login);
        $this->assertStringContainsString('$(chap-id)', $login);
        $this->assertStringContainsString('window.location.replace', $login);
        $this->assertStringNotContainsString('Rudi kwenye voucher', $login);
        $this->assertStringNotContainsString('hexMD5', $login);
        $this->assertStringNotContainsString('Continue', $login);
    }

    #[Test]
    public function changing_the_router_ip_rewrites_the_radius_client_row(): void
    {
        [$owner, $nas] = $this->router('10.10.10.1');

        $this->actingAs($owner)
            ->patchJson("/api/v1/nas-devices/{$nas->id}", [
                'nasname' => '102.202.74.55',
            ])
            ->assertOk()
            ->assertJsonPath('data.nasname', '102.202.74.55');

        $this->assertDatabaseMissing('nas', ['nasname' => '10.10.10.1']);
        $this->assertDatabaseHas('nas', ['nasname' => '102.202.74.55']);
        $this->assertSame($nas->fresh()->shared_secret, Nas::query()->where('nasname', '102.202.74.55')->value('secret'));
    }

    /**
     * @return array{0: User, 1: NasDevice}
     */
    private function router(string $nasname, ?string $apiHost = null): array
    {
        $tenant = Tenant::factory()->create();
        $this->actingForTenant($tenant);
        $owner = User::factory()->for($tenant)->owner()->create();
        $site = Site::factory()->for($tenant)->create(['nas_identifier' => 'kasi-demo-site-1']);
        $nas = NasDevice::factory()->for($site)->create([
            'tenant_id' => $tenant->id,
            'nasname' => $nasname,
            'api_host' => $apiHost,
        ]);

        app(NasSynchroniser::class)->sync($nas);

        return [$owner, $nas];
    }
}
