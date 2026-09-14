<?php

declare(strict_types=1);

namespace App\Domain\Voucher;

enum BillingPeriod: string
{
    case Hourly = 'hourly';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    /** Operator-defined window that none of the presets describe. */
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Hourly => 'Hourly',
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
            self::Custom => 'Custom',
        };
    }

    /**
     * The wall-clock window a bundle of this period is worth, counted from the
     * client's first login rather than from purchase.
     *
     * Custom carries no implied length; the operator supplies validity_seconds.
     */
    public function validitySeconds(): ?int
    {
        return match ($this) {
            self::Hourly => 3600,
            self::Daily => 86400,
            self::Weekly => 604800,
            self::Monthly => 2592000,
            self::Custom => null,
        };
    }
}
