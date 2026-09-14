<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

enum UserRole: string
{
    /** Operates the platform itself and is not confined to one tenant. */
    case PlatformAdmin = 'platform_admin';

    /** Full control of a single operator, including billing credentials. */
    case Owner = 'owner';

    /** Day-to-day operator staff: bundles, vouchers, routers, sessions. */
    case Staff = 'staff';

    /**
     * Sells printed vouchers at a counter. Deliberately limited to printing
     * batches assigned to them; agents cannot see revenue, other agents' stock,
     * or anything that would let them issue vouchers themselves.
     */
    case Agent = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::PlatformAdmin => 'Platform administrator',
            self::Owner => 'Owner',
            self::Staff => 'Staff',
            self::Agent => 'Agent',
        };
    }

    /**
     * Whether this role may administer an operator's configuration.
     */
    public function managesTenant(): bool
    {
        return in_array($this, [self::PlatformAdmin, self::Owner, self::Staff], true);
    }

    public function isAgent(): bool
    {
        return $this === self::Agent;
    }
}
