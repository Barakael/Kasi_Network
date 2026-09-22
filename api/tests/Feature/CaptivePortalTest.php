<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CaptivePortalTest extends TestCase
{
    #[Test]
    public function it_advertises_the_hotspot_login_page(): void
    {
        $response = $this->get('/captive-portal');

        $response->assertOk();
        $this->assertStringContainsString(
            'application/captive+json',
            (string) $response->headers->get('Content-Type'),
        );
        $response->assertJson([
            'captive' => true,
            'user-portal-url' => 'http://192.168.88.1/login',
        ]);
    }
}
