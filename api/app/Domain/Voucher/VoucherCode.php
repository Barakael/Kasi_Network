<?php

declare(strict_types=1);

namespace App\Domain\Voucher;

use InvalidArgumentException;

/**
 * Generation, normalisation and validation of voucher codes.
 *
 * Codes are read off a printed card and typed on a phone keyboard, often by
 * someone who did not print them. The default alphabet is Crockford base32: it
 * has no O/0, I/1 or L/1 pairs to confuse, and it defines the substitutions to
 * accept when a user types the wrong one of a look-alike pair anyway.
 *
 * Operators may instead choose 6-digit numeric codes or letters-only codes in
 * Settings. A trailing check character (alphanumeric / alpha modes) makes a
 * mistyped code fail before a round trip. Numeric codes skip the check and rely
 * on the redeem attempt lock instead.
 */
final class VoucherCode
{
    public const string CHARSET_NUMERIC = 'numeric';

    public const string CHARSET_ALPHA = 'alpha';

    public const string CHARSET_ALPHANUMERIC = 'alphanumeric';

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

    /** Letters only (Crockford letters, no digits). */
    public const string ALPHABET_ALPHA = 'ABCDEFGHJKMNPQRSTVWXY';

    public const string ALPHABET_NUMERIC = '0123456789';

    /** Printed length for numeric codes (no check character). */
    public const int NUMERIC_LENGTH = 6;

    /**
     * @return list<string>
     */
    public static function charsets(): array
    {
        return [
            self::CHARSET_NUMERIC,
            self::CHARSET_ALPHA,
            self::CHARSET_ALPHANUMERIC,
        ];
    }

    public static function isValidCharset(string $charset): bool
    {
        return in_array($charset, self::charsets(), true);
    }

    /**
     * Builds a strong random code for the given owner charset setting.
     */
    public static function generateForCharset(string $charset, ?int $bodyLength = null): string
    {
        return match ($charset) {
            self::CHARSET_NUMERIC => self::generateNumeric(),
            self::CHARSET_ALPHA => self::generateWithAlphabet(
                self::ALPHABET_ALPHA,
                $bodyLength ?? 9,
            ),
            self::CHARSET_ALPHANUMERIC => self::generate($bodyLength ?? 9),
            default => throw new InvalidArgumentException("Unknown voucher charset: {$charset}"),
        };
    }

    /**
     * Builds a strong random alphanumeric code: random body, then a check character.
     *
     * Codes are not prefixed with a shared tenant tag. Every card must look
     * different; tenant ownership is enforced by tenant_id + HMAC lookup, not by
     * a visible prefix that would make every voucher start the same way.
     *
     * @param  int  $bodyLength  Random characters before the check character.
     *                           Default 9 → printed code length 10.
     * @param  string  $prefix  Optional legacy prefix; leave empty for new issues.
     */
    public static function generate(int $bodyLength = 9, string $prefix = ''): string
    {
        if ($prefix !== '' && ! self::isValidPrefix($prefix)) {
            throw new InvalidArgumentException("Invalid voucher prefix: {$prefix}");
        }

        $body = self::generateWithAlphabet(self::ALPHABET, $bodyLength);

        if ($prefix === '') {
            return $body;
        }

        $withoutCheck = strtoupper($prefix).substr($body, 0, -1);

        return $withoutCheck.self::checkCharacter($withoutCheck, self::ALPHABET);
    }

    public static function generateNumeric(int $length = self::NUMERIC_LENGTH): string
    {
        if ($length < 1) {
            throw new InvalidArgumentException('Numeric code length must be at least 1.');
        }

        $alphabet = self::ALPHABET_NUMERIC;
        $max = strlen($alphabet) - 1;
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }

        return $code;
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
     * Whether a typed code is a plausible voucher for any supported charset.
     *
     * Used at redeem so a charset change does not block already-printed cards:
     * numeric (6 digits), alpha-with-check, or alphanumeric-with-check all pass.
     */
    public static function isPlausible(string $code): bool
    {
        $normalised = self::normalise($code);

        if ($normalised === '') {
            return false;
        }

        if (preg_match('/^\d{'.self::NUMERIC_LENGTH.'}$/', $normalised) === 1) {
            return true;
        }

        if (self::hasValidCheckCharacter($normalised, self::ALPHABET)) {
            return true;
        }

        return self::hasValidCheckCharacter($normalised, self::ALPHABET_ALPHA);
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
    public static function checkCharacter(string $codeWithoutCheck, string $alphabet = self::ALPHABET): string
    {
        $normalised = self::normalise($codeWithoutCheck);
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
     * A false result means the code was mistyped, not that it is unknown.
     */
    public static function hasValidCheckCharacter(string $code, string $alphabet = self::ALPHABET): bool
    {
        $normalised = self::normalise($code);

        if (strlen($normalised) < 2) {
            return false;
        }

        $body = substr($normalised, 0, -1);
        $check = substr($normalised, -1);

        try {
            return self::checkCharacter($body, $alphabet) === $check;
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

    private static function generateWithAlphabet(string $alphabet, int $bodyLength): string
    {
        if ($bodyLength < 1) {
            throw new InvalidArgumentException('Body length must be at least 1.');
        }

        $max = strlen($alphabet) - 1;
        $body = '';

        for ($i = 0; $i < $bodyLength; $i++) {
            // random_int is cryptographically secure; mt_rand would make codes
            // predictable from a handful of observed vouchers.
            $body .= $alphabet[random_int(0, $max)];
        }

        return $body.self::checkCharacter($body, $alphabet);
    }
}
