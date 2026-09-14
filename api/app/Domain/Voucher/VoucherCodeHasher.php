<?php

declare(strict_types=1);

namespace App\Domain\Voucher;

use RuntimeException;

/**
 * Derives the deterministic lookup value for a voucher code.
 *
 * Voucher codes are stored encrypted, and Laravel's encryption is randomised, so
 * an encrypted column cannot be searched. Redemption therefore looks a voucher up
 * by a keyed HMAC of its normalised code.
 *
 * The HMAC is keyed rather than a bare hash on purpose. A voucher body is ten
 * characters of base32, about 2^50 possibilities, which a plain SHA-256 column
 * would let an attacker with a database dump exhaust on commodity GPUs in around
 * a day. Keying it means a dump alone is useless without the separate key.
 */
final readonly class VoucherCodeHasher
{
    public function __construct(private string $key)
    {
        if ($this->key === '') {
            throw new RuntimeException(
                'Voucher hash key is not configured. Set KASI_VOUCHER_HASH_KEY.'
            );
        }
    }

    /**
     * @return string 64 hex characters.
     */
    public function hash(string $code): string
    {
        return hash_hmac('sha256', VoucherCode::normalise($code), $this->key);
    }

    /**
     * Compares in constant time, so a timing side channel cannot be used to
     * discover a valid code one character at a time.
     */
    public function matches(string $code, string $expectedHash): bool
    {
        return hash_equals($expectedHash, $this->hash($code));
    }
}
