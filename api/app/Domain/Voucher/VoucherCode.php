<?php

declare(strict_types=1);

namespace App\Domain\Voucher;

use InvalidArgumentException;

/**
 * Generation, normalisation and validation of voucher codes.
 *
 * Codes are read off a printed card and typed on a phone keyboard, often by
 * someone who did not print them, so the alphabet is Crockford base32: it has no
 * O/0, I/1 or L/1 pairs to confuse, and it defines the substitutions to accept
 * when a user types the wrong one of a look-alike pair anyway.
 *
 * A trailing check character makes a mistyped code fail in the browser instead of
 * costing a round trip. That matters more than it sounds: codes double as RADIUS
 * credentials, so every guess that reaches the API has to be rate limited, and
 * filtering out honest typos first keeps that budget for actual attacks.
 */
final class VoucherCode
{
    /**
     * Crockford base32 minus Z, leaving 31 symbols. Excludes I, L, O and U.
     *
     * The size being prime is what makes the check character work. With a
     * composite alphabet such as the full 32 symbols, a weighted checksum taken
     * modulo the alphabet size misses some single-character substitutions,
     * because a weight sharing a factor with 32 can cancel the error. 31 is
     * prime, so no weight below it can, and every single-character substitution
     * changes the result. Dropping one symbol costs nothing: a ten-character
     * body still yields about 8.2e14 combinations.
     */
    public const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXY';

    /**
     * Builds a code for a tenant: prefix, random body, then a check character.
     *
     * @param  string  $prefix  Tenant code prefix, already validated.
     * @param  int  $bodyLength  Random characters between prefix and check character.
     */
    public static function generate(string $prefix, int $bodyLength): string
    {
        $alphabet = self::ALPHABET;
        $max = strlen($alphabet) - 1;
        $body = '';

        for ($i = 0; $i < $bodyLength; $i++) {
            // random_int is cryptographically secure; mt_rand would make codes
            // predictable from a handful of observed vouchers.
            $body .= $alphabet[random_int(0, $max)];
        }

        $withoutCheck = strtoupper($prefix).$body;

        return $withoutCheck.self::checkCharacter($withoutCheck);
    }

    /**
     * Canonical form for hashing and comparison.
     *
     * Separators are dropped so "KAS-7F3K-9M2Q" and "kas7f3k9m2q" are the same
     * code, and the Crockford look-alikes are folded onto the symbol they stand
     * in for.
     */
    public static function normalise(string $code): string
    {
        $upper = strtoupper(trim($code));

        // Keep only characters that could be part of a code before folding, so
        // dashes, spaces and stray punctuation disappear.
        $stripped = (string) preg_replace('/[^0-9A-Z]/', '', $upper);

        return strtr($stripped, [
            'O' => '0',
            'I' => '1',
            'L' => '1',
        ]);
    }

    /**
     * Position-weighted checksum over the code, rendered in the same alphabet.
     *
     * Each character's value is multiplied by its 1-based position and the total
     * is taken modulo the alphabet size. Over a prime-sized alphabet this catches
     * every single-character substitution and every transposition of adjacent
     * characters, which together account for nearly all typing mistakes:
     *
     *   - Substituting one character changes the total by w * (v' - v). Neither
     *     the weight nor the value difference can be a multiple of 31, so the
     *     total always moves.
     *   - Swapping neighbours changes it by (w_i - w_j) * (v_j - v_i), and
     *     adjacent weights differ by exactly 1, so again it always moves.
     *
     * Weighting by position is essential; an unweighted sum would miss every
     * transposition, since addition does not care about order.
     */
    public static function checkCharacter(string $codeWithoutCheck): string
    {
        $normalised = self::normalise($codeWithoutCheck);
        $alphabet = self::ALPHABET;
        $base = strlen($alphabet);
        $sum = 0;

        /*
         * The substitution guarantee holds only while no position weight is
         * itself a multiple of the alphabet size. Codes are prefix plus ten
         * characters, far short of this, but a future longer format would
         * silently weaken the checksum rather than fail, so it is asserted.
         */
        if (strlen($normalised) >= $base) {
            throw new InvalidArgumentException(
                "Code too long for a single check character: {$base} characters maximum."
            );
        }

        foreach (str_split($normalised) as $index => $character) {
            $value = strpos($alphabet, $character);

            if ($value === false) {
                throw new InvalidArgumentException("Character not in alphabet: {$character}");
            }

            $sum += ($index + 1) * $value;
        }

        return $alphabet[$sum % $base];
    }

    /**
     * Whether a code's check character agrees with its body.
     *
     * A false result means the code was mistyped, not that it is unknown, so the
     * portal can say so without consuming a redemption attempt.
     */
    public static function hasValidCheckCharacter(string $code): bool
    {
        $normalised = self::normalise($code);

        if (strlen($normalised) < 2) {
            return false;
        }

        $body = substr($normalised, 0, -1);
        $check = substr($normalised, -1);

        try {
            return self::checkCharacter($body) === $check;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Splits a code into dash-separated groups for printing.
     */
    public static function forDisplay(string $code, int $groupSize = 4): string
    {
        $normalised = self::normalise($code);

        if ($groupSize < 1) {
            return $normalised;
        }

        return implode('-', str_split($normalised, $groupSize));
    }

    /**
     * The last group of a code, stored so support staff can find a voucher from
     * what a caller reads out without decrypting the table.
     */
    public static function suffix(string $code, int $length = 4): string
    {
        return substr(self::normalise($code), -$length);
    }

    /**
     * Whether a tenant prefix is safe to build codes from.
     *
     * A prefix containing I, L or O would be folded by normalisation into a
     * different string than was printed, so those are rejected at the source
     * rather than producing codes that cannot be redeemed.
     */
    public static function isValidPrefix(string $prefix): bool
    {
        if ($prefix === '') {
            return false;
        }

        return preg_match('/^['.self::ALPHABET.']+$/', strtoupper($prefix)) === 1;
    }
}
