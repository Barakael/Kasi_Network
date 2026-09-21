<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Domain\Support\MacAddress;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\PortalToken;
use App\Http\Requests\Portal\BootstrapRequest;
use App\Http\Resources\PlanResource;
use App\Models\NasDevice;
use App\Models\Plan;
use App\Models\Site;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

/**
 * Opens a captive-portal session.
 *
 * The only portal endpoint reachable without a token. It turns the router
 * identifier written into login.html into a resolved operator, and returns the
 * signed token every later call must present, alongside the branding and bundle
 * list needed to render the first screen in one round trip -- the client is on a
 * constrained link and has not paid yet.
 */
class BootstrapController
{
    public function __construct(
        private readonly CurrentTenant $currentTenant,
        private readonly PortalToken $portalToken,
    ) {}

    public function __invoke(BootstrapRequest $request): JsonResponse
    {
        /*
         * No tenant is resolved yet -- this is the request that resolves one --
         * so the lookup runs unscoped.
         */
        $site = $this->currentTenant->withoutTenant(
            fn (): ?Site => Site::query()
                ->with('tenant')
                ->where('nas_identifier', $request->string('site'))
                ->first(),
        );

        /*
         * A wrong identifier and a suspended operator return the same 404. Which
         * of the two it was is not information an unauthenticated client on the
         * network needs.
         */
        if ($site === null || ! $site->isActive() || ! $site->tenant->isActive()) {
            return response()->json([
                'message' => 'This hotspot is not available right now.',
                'code' => 'site_unavailable',
            ], 404);
        }

        $this->currentTenant->set($site->tenant);

        $clientMac = MacAddress::tryParse($request->string('mac')->value());
        $nasDevice = $this->resolveRouter($site, $request);

        $token = $this->portalToken->issue(
            site: $site,
            nasDevice: $nasDevice,
            clientMac: $clientMac?->toString(),
            clientIp: $request->string('ip')->value() ?: $request->ip(),
        );

        return response()->json([
            'token' => $token,
            'site' => [
                'name' => $site->name,
                'ssid' => $site->ssid,
            ],
            'branding' => [
                'operator' => $site->tenant->portal_name ?? $site->tenant->name,
                'primary_color' => $site->tenant->primary_color,
                'support_phone' => $site->tenant->support_phone,
                'currency' => $site->tenant->currency,
            ],
            'client' => [
                'mac' => $clientMac?->toString(),
                // Echoed back so the portal knows whether it can offer to bind a
                // second device, which needs a MAC to work from.
                'mac_known' => $clientMac !== null,
            ],
            'capabilities' => [
                'online_payments' => $site->tenant->acceptsOnlinePayments(),
                'demo_checkout' => (bool) config('kasi.demo_checkout'),
                /*
                 * Listing nearby devices to bind a TV to needs the RouterOS API.
                 * Without it the portal falls back to typing a MAC by hand.
                 */
                'device_discovery' => $nasDevice?->hasApiCredentials() ?? false,
            ],
            'plans' => PlanResource::collection($this->plansFor()),
        ]);
    }

    /**
     * Identifies which of the site's routers the client is behind.
     *
     * Needed later to read the hotspot host table and to address CoA messages. A
     * single-router site is the common case and needs no matching at all.
     */
    private function resolveRouter(Site $site, BootstrapRequest $request): ?NasDevice
    {
        $devices = NasDevice::query()
            ->where('site_id', $site->id)
            ->where('status', 'active')
            ->get();

        if ($devices->count() <= 1) {
            return $devices->first();
        }

        $linkLogin = $request->string('link_login')->value();

        if ($linkLogin !== '') {
            $host = parse_url($linkLogin, PHP_URL_HOST);

            $matched = $devices->firstWhere('nasname', $host);

            if ($matched instanceof NasDevice) {
                return $matched;
            }
        }

        return $devices->first();
    }

    /**
     * @return Collection<int, Plan>
     */
    private function plansFor()
    {
        return Plan::query()
            ->where('is_active', true)
            ->where('is_sold_online', true)
            ->orderBy('sort_order')
            ->orderBy('price_minor')
            ->get();
    }
}
