<?php

declare(strict_types=1);

namespace App\Domain\Voucher;

enum QuotaAction: string
{
    /** End the session with a RADIUS Disconnect-Request. */
    case Disconnect = 'disconnect';

    /**
     * Leave the session up but push a reduced Mikrotik-Rate-Limit via CoA.
     * Preferred for monthly bundles, where cutting a customer off entirely
     * tends to generate a support call rather than an upgrade.
     */
    case Throttle = 'throttle';

    public function label(): string
    {
        return match ($this) {
            self::Disconnect => 'Disconnect the session',
            self::Throttle => 'Reduce speed',
        };
    }
}
