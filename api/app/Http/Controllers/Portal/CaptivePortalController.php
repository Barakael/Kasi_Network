<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * RFC 8908 API Windows 11 reads from DHCP option 114. HTML at that URL
 * makes Windows hide the sign-in window; this JSON is what it expects.
 */
final class CaptivePortalController extends Controller
{
    public function __invoke(): Response
    {
        return response(
            json_encode([
                'captive' => true,
                'user-portal-url' => 'http://192.168.88.1/login',
            ], JSON_UNESCAPED_SLASHES),
            200,
            [
                'Content-Type' => 'application/captive+json',
                'Cache-Control' => 'no-store',
            ],
        );
    }
}
