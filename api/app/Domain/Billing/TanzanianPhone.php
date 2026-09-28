<?php

declare(strict_types=1);

namespace App\Domain\Billing;

/**
 * Tanzanian mobile numbers as stored on orders (255XXXXXXXXX) and as sent to
 * PalmPesa initiate (07XXXXXXXX / 06XXXXXXXX).
 */
final class TanzanianPhone
{
    public static function toE164(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (str_starts_with($digits, '255') && strlen($digits) === 12) {
            return $digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '255'.substr($digits, 1);
        }

        if (strlen($digits) === 9) {
            return '255'.$digits;
        }

        return $digits;
    }

    public static function toLocal(string $raw): string
    {
        $e164 = self::toE164($raw);

        if (str_starts_with($e164, '255') && strlen($e164) === 12) {
            return '0'.substr($e164, 3);
        }

        return $raw;
    }

    public static function isValid(string $raw): bool
    {
        $e164 = self::toE164($raw);

        if (strlen($e164) !== 12 || ! str_starts_with($e164, '255')) {
            return false;
        }

        return in_array($e164[3], ['6', '7'], true);
    }

    public static function mask(string $raw): string
    {
        $local = self::toLocal($raw);

        if (strlen($local) < 4) {
            return '****';
        }

        return '***'.substr($local, -4);
    }
}
