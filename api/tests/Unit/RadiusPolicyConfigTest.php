<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RadiusPolicyConfigTest extends TestCase
{
    #[Test]
    public function authorize_matches_packet_source_or_lan_api_host(): void
    {
        $site = $this->raddb('sites-available/kasi');

        $this->assertStringContainsString("nd.nasname = '%{Packet-Src-IP-Address}'", $site);
        $this->assertStringContainsString("nd.nasname = '%{NAS-IP-Address}'", $site);
        $this->assertStringContainsString("nd.api_host = '%{NAS-IP-Address}'", $site);
        $this->assertStringContainsString("s.nas_identifier = '%{NAS-Identifier}'", $site);
        $this->assertStringContainsString('port = 2083', $site);
        $this->assertStringContainsString('proto = tcp', $site);
        $this->assertStringContainsString('proto = tls', $site);
    }

    #[Test]
    public function mac_rewrite_matches_packet_source_or_lan_api_host(): void
    {
        $policy = $this->raddb('policy.d/kasi');

        $this->assertStringContainsString('normalize_calling_station_id', $policy);
        $this->assertStringContainsString("nd.nasname = '%{Packet-Src-IP-Address}'", $policy);
        $this->assertStringContainsString("nd.api_host = '%{NAS-IP-Address}'", $policy);
        $this->assertStringNotContainsString("AND nd.nasname = '%{NAS-IP-Address}' AND nd.deleted_at IS NULL LIMIT 1", $policy);
    }

    #[Test]
    public function authorize_normalizes_calling_station_id_before_mac_rewrite(): void
    {
        $site = $this->raddb('sites-available/kasi');

        $normalizeAt = strpos($site, 'normalize_calling_station_id');
        $rewriteAt = strpos($site, 'rewrite_mac_username');

        $this->assertNotFalse($normalizeAt);
        $this->assertNotFalse($rewriteAt);
        $this->assertLessThan($rewriteAt, $normalizeAt);
    }

    private function raddb(string $relative): string
    {
        $path = dirname(__DIR__, 3).'/docker/freeradius/config/'.$relative;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
