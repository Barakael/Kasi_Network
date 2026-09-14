<?php

declare(strict_types=1);

namespace App\Domain\Voucher;

enum BatchStatus: string
{
    /** Codes are still being written by the issuing job. */
    case Generating = 'generating';

    /** All codes issued and printable. */
    case Ready = 'ready';

    /** Handed to an agent for sale. */
    case Distributed = 'distributed';

    /** Withdrawn; every voucher in the batch is refused. */
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Generating => 'Generating',
            self::Ready => 'Ready',
            self::Distributed => 'Distributed',
            self::Disabled => 'Disabled',
        };
    }

    public function isPrintable(): bool
    {
        return in_array($this, [self::Ready, self::Distributed], true);
    }
}
