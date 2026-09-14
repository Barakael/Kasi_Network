<?php

declare(strict_types=1);

namespace App\Domain\Support;

use InvalidArgumentException;
use Stringable;

/**
 * A normalised MAC address.
 *
 * MACs reach this system from several directions that all format them
 * differently: RouterOS sends Calling-Station-Id as it is configured to, the
 * hotspot host table returns its own casing, and a user binding a smart TV types
 * whatever is printed on the sticker. Comparing those forms as raw strings is how
 * MAC binding silently stops matching, so every MAC is funnelled through here
 * first.
 */
final readonly class MacAddress implements Stringable
{
    /**
     * @param  string  $hex  Twelve uppercase hex digits, no separators.
     */
    private function __construct(private string $hex) {}

    /**
     * @throws InvalidArgumentException if the value is not a MAC address.
     */
    public static function parse(string $value): self
    {
        $hex = strtoupper((string) preg_replace('/[^0-9A-Fa-f]/', '', $value));

        if (strlen($hex) !== 12) {
            throw new InvalidArgumentException("Not a MAC address: {$value}");
        }

        return new self($hex);
    }

    public static function tryParse(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return self::parse($value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public static function isValid(string $value): bool
    {
        return self::tryParse($value) instanceof self;
    }

    /**
     * Canonical storage and comparison form: AA:BB:CC:DD:EE:FF.
     */
    public function toString(): string
    {
        return implode(':', str_split($this->hex, 2));
    }

    /**
     * Renders the address the way a router is configured to send it.
     *
     * The pattern mirrors RouterOS's `radius-mac-format` values, so a change on
     * the router is matched by a change to config('kasi.radius.mac_format')
     * rather than by editing comparison code.
     *
     * @param  string  $format  e.g. XX:XX:XX:XX:XX:XX, XX-XX-XX-XX-XX-XX, XXXXXXXXXXXX
     */
    public function format(string $format): string
    {
        $digits = str_split($this->hex);
        $out = '';
        $index = 0;

        foreach (str_split($format) as $char) {
            if ($char === 'X' || $char === 'x') {
                $digit = $digits[$index] ?? '0';
                $out .= $char === 'x' ? strtolower($digit) : $digit;
                $index++;

                continue;
            }

            $out .= $char;
        }

        return $out;
    }

    /**
     * The IEEE OUI prefix, used to look up the device vendor so a user picking a
     * device to bind sees "Samsung Electronics" rather than a hex string.
     */
    public function oui(): string
    {
        return substr($this->hex, 0, 6);
    }

    public function equals(self $other): bool
    {
        return $this->hex === $other->hex;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
