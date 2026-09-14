<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\PortalContext;
use App\Domain\Tenancy\PortalToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Establishes which operator an unauthenticated portal request belongs to.
 *
 * Requires the signed token issued by the bootstrap endpoint. Rejecting requests
 * without one is what stops a client redeeming a voucher against a site other
 * than the one they are physically connected to.
 */
class ResolvePortalContext
{
    public const string HEADER = 'X-Kasi-Portal-Token';

    public function __construct(
        private CurrentTenant $currentTenant,
        private PortalToken $portalToken,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $context = $this->portalToken->parse($request->header(self::HEADER));

        if (! $context instanceof PortalContext) {
            return response()->json([
                'message' => 'This session has expired. Reconnect to the WiFi to continue.',
                'code' => 'portal_session_expired',
            ], 419);
        }

        $this->currentTenant->set($context->tenant);
        app()->instance(PortalContext::class, $context);

        return $next($request);
    }
}
