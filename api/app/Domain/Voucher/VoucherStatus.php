<?php

declare(strict_types=1);

namespace App\Domain\Voucher;

enum VoucherStatus: string
{
    /** Issued but never authenticated. The clock has not started. */
    case Unused = 'unused';

    /** Redeemed at least once and still within its window and quota. */
    case Active = 'active';

    /** Time or data allowance is spent. */
    case Exhausted = 'exhausted';

    /** Validity window elapsed, or unsold stock passed its shelf life. */
    case Expired = 'expired';

    /** Withdrawn by an operator, e.g. a batch reported stolen. */
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Unused => 'Unused',
            self::Active => 'Active',
            self::Exhausted => 'Used up',
            self::Expired => 'Expired',
            self::Disabled => 'Disabled',
        };
    }

    /**
     * Whether a voucher in this state can still authenticate a client.
     */
    public function isUsable(): bool
    {
        return in_array($this, [self::Unused, self::Active], true);
    }

    /**
     * States that no longer count as sellable or usable stock.
     */
    public function isTerminal(): bool
    {
        return ! $this->isUsable();
    }
}
