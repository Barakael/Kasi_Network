<?php

declare(strict_types=1);

namespace App\Domain\Billing;

enum PalmPesaPaymentStatus: string
{
    case Pending = 'PENDING';
    case Completed = 'COMPLETED';
    case Failed = 'FAILED';

    public static function fromRemote(string $value): self
    {
        return match (strtoupper(trim($value))) {
            'COMPLETED', 'SUCCESS', 'SUCCESSFUL', 'PAID' => self::Completed,
            'FAILED', 'FAILURE', 'CANCELLED', 'CANCELED' => self::Failed,
            default => self::Pending,
        };
    }
}
