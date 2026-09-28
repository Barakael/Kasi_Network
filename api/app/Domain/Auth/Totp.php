<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use InvalidArgumentException;

/**
 * RFC 6238 TOTP using SHA-1 and a 30-second window.
 *
 * No extra package: the algorithm is small, and Bacon already draws the QR we
 * show the System Administrator when they turn this on.
 */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function secret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function code(string $secret, ?int $timestamp = null): string
    {
        $slice = intdiv($timestamp ?? time(), 30);
        $key = self::base32Decode($secret);
        $time = pack('N*', 0).pack('N*', $slice);
        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $truncated = unpack('N', substr($hash, $offset, 4));
        $value = ($truncated[1] & 0x7FFFFFFF) % 1_000_000;

        return str_pad((string) $value, 6, '0', STR_PAD_LEFT);
    }

    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $digits = preg_replace('/\D+/', '', $code) ?? '';

        if (strlen($digits) !== 6) {
            return false;
        }

        $now = time();

        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::code($secret, $now + ($i * 30)), $digits)) {
                return true;
            }
        }

        return false;
    }

    public static function otpauthUrl(string $secret, string $email, string $issuer = 'Kasi'): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&period=30&digits=6',
            rawurlencode($issuer),
            rawurlencode($email),
            $secret,
            rawurlencode($issuer),
        );
    }

    public static function base32Encode(string $binary): string
    {
        $bits = '';

        foreach (str_split($binary) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';

        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0');
            }

            $encoded .= self::ALPHABET[bindec($chunk)];
        }

        return $encoded;
    }

    public static function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        $bits = '';

        foreach (str_split($secret) as $char) {
            $index = strpos(self::ALPHABET, $char);

            if ($index === false) {
                throw new InvalidArgumentException('Invalid authenticator secret.');
            }

            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';

        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) < 8) {
                break;
            }

            $binary .= chr(bindec($chunk));
        }

        return $binary;
    }
}
