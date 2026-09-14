<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use App\Models\NasDevice;
use App\Models\Site;
use App\Models\Tenant;

/**
 * Who and where an unauthenticated captive-portal client is.
 *
 * Established once by the bootstrap endpoint and carried on subsequent calls in a
 * signed token, so the portal never has to be trusted to say which operator it is
 * acting for.
 */
final readonly class PortalContext
{
    public function __construct(
        public Tenant $tenant,
        public Site $site,
        public ?NasDevice $nasDevice = null,
        public ?string $clientMac = null,
        public ?string $clientIp = null,
    ) {}

    /**
     * The router this client is connected through, needed to send it a CoA or to
     * read its hotspot host table.
     */
    public function hasKnownRouter(): bool
    {
        return $this->nasDevice instanceof NasDevice;
    }
}
