<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Domain\Billing\TanzanianPhone;
use App\Domain\Customers\CustomerDirectory;
use App\Domain\Support\MacAddress;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\PortalContext;
use App\Domain\Tenancy\PortalPayload;
use App\Domain\Tenancy\PortalToken;
use App\Http\Requests\Portal\BootstrapRequest;
use App\Models\NasDevice;
use App\Models\Site;
use Illuminate\Http\JsonResponse;

/**
 * Opens a captive-portal session.
 *
 * The only portal endpoint reachable without a token. It turns the router
 * identifier written into login.html into a resolved operator, and returns the
 * signed token every later call must present, alongside the branding. Packages
 * stay hidden until a Tanzanian phone is saved for this operator.
 */
class BootstrapController
{
    public function __construct(
        private readonly CurrentTenant $currentTenant,
        private readonly PortalToken $portalToken,
        private readonly CustomerDirectory $customers,
        private readonly PortalPayload $payload,
    ) {}

    public function __invoke(BootstrapRequest $request): JsonResponse
    {
        $site = $this->currentTenant->withoutTenant(
            fn (): ?Site => Site::query()
                ->with('tenant')
                ->where('nas_identifier', $request->string('site'))
                ->first(),
        );

        if ($site === null || ! $site->isActive() || ! $site->tenant->isActive()) {
            return response()->json([
                'message' => 'This hotspot is not available right now.',
                'code' => 'site_unavailable',
            ], 404);
        }

        $this->currentTenant->set($site->tenant);

        $clientMac = MacAddress::tryParse($request->string('mac')->value());
        $nasDevice = $this->resolveRouter($site, $request);

        $context = new PortalContext(
            tenant: $site->tenant,
            site: $site,
            nasDevice: $nasDevice,
            clientMac: $clientMac?->toString(),
            clientIp: $request->string('ip')->value() ?: $request->ip(),
        );

        $phone = $request->string('phone')->value();

        if ($phone !== '' && TanzanianPhone::isValid($phone)) {
            $customer = $this->customers->identify($context, $phone);
            $context = $context->withCustomer($customer->id);
        }

        return $this->payload->json($context, $nasDevice);
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
}
