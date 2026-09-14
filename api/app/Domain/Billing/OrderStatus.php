<?php

declare(strict_types=1);

namespace App\Domain\Billing;

enum OrderStatus: string
{
    /** Created locally; the Snippe payment intent has not been accepted yet. */
    case Pending = 'pending';

    /** Snippe accepted the intent and the customer has a USSD prompt open. */
    case AwaitingPayment = 'awaiting_payment';

    /** Funds confirmed, voucher not yet provisioned onto the router. */
    case Paid = 'paid';

    /** Voucher provisioned and released to the portal. */
    case Fulfilled = 'fulfilled';

    /** Declined, wrong PIN, insufficient funds. */
    case Failed = 'failed';

    /** Customer never authorised the push before Snippe expired it. */
    case Expired = 'expired';

    /** Cancelled upstream before completion. */
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Starting payment',
            self::AwaitingPayment => 'Waiting for confirmation',
            self::Paid => 'Paid',
            self::Fulfilled => 'Complete',
            self::Failed => 'Failed',
            self::Expired => 'Expired',
            self::Voided => 'Cancelled',
        };
    }

    /**
     * Whether the portal should keep polling this order.
     */
    public function isSettled(): bool
    {
        return ! in_array($this, [self::Pending, self::AwaitingPayment], true);
    }

    /**
     * Whether a voucher reserved for this order should be released back to stock.
     */
    public function releasesVoucher(): bool
    {
        return in_array($this, [self::Failed, self::Expired, self::Voided], true);
    }

    /**
     * Maps a Snippe payment status onto the local lifecycle.
     */
    public static function fromSnippeStatus(string $status): self
    {
        return match ($status) {
            'completed' => self::Paid,
            'failed' => self::Failed,
            'expired' => self::Expired,
            'voided', 'cancelled' => self::Voided,
            default => self::AwaitingPayment,
        };
    }
}
