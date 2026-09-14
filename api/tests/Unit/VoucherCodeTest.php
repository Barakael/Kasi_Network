<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Voucher\VoucherCode;
use App\Domain\Voucher\VoucherCodeHasher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class VoucherCodeTest extends TestCase
{
    public function test_generated_code_starts_with_the_tenant_prefix(): void
    {
        $code = VoucherCode::generate('KAS', 10);

        $this->assertStringStartsWith('KAS', $code);
    }

    public function test_generated_code_is_prefix_plus_body_plus_check_character(): void
    {
        $code = VoucherCode::generate('KAS', 10);

        $this->assertSame(14, strlen($code));
    }

    public function test_generated_codes_pass_their_own_check_character(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $code = VoucherCode::generate('KAS', 10);

            $this->assertTrue(
                VoucherCode::hasValidCheckCharacter($code),
                "Generated code failed its own checksum: {$code}",
            );
        }
    }

    public function test_generated_codes_only_use_the_crockford_alphabet(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $code = VoucherCode::generate('KAS', 10);

            $this->assertMatchesRegularExpression(
                '/^['.VoucherCode::ALPHABET.']+$/',
                $code,
                "Code contains a character outside the alphabet: {$code}",
            );
        }
    }

    public function test_generated_codes_are_not_repeated(): void
    {
        $codes = [];

        for ($i = 0; $i < 500; $i++) {
            $codes[] = VoucherCode::generate('KAS', 10);
        }

        $this->assertCount(500, array_unique($codes));
    }

    #[DataProvider('equivalentCodes')]
    public function test_normalisation_folds_separators_and_look_alike_characters(
        string $input,
        string $expected,
    ): void {
        $this->assertSame($expected, VoucherCode::normalise($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function equivalentCodes(): array
    {
        return [
            'lowercase' => ['kas7f3k9m2q', 'KAS7F3K9M2Q'],
            'dashes' => ['KAS-7F3K-9M2Q', 'KAS7F3K9M2Q'],
            'spaces' => ['KAS 7F3K 9M2Q', 'KAS7F3K9M2Q'],
            'surrounding whitespace' => ['  KAS7F3K9M2Q  ', 'KAS7F3K9M2Q'],
            'letter O typed for zero' => ['KASO', 'KAS0'],
            'letter I typed for one' => ['KASI', 'KAS1'],
            'letter L typed for one' => ['KASL', 'KAS1'],
        ];
    }

    /**
     * Exhaustive rather than sampled: every position substituted for every other
     * character in the alphabet. A weaker checksum passes a spot check and then
     * lets real typos through, so the guarantee is verified in full.
     */
    public function test_every_single_character_substitution_fails_the_check_character(): void
    {
        $code = VoucherCode::generate('KAS', 10);
        $checked = 0;

        for ($position = 0; $position < strlen($code); $position++) {
            foreach (str_split(VoucherCode::ALPHABET) as $replacement) {
                if ($code[$position] === $replacement) {
                    continue;
                }

                $mistyped = substr_replace($code, $replacement, $position, 1);
                $checked++;

                $this->assertFalse(
                    VoucherCode::hasValidCheckCharacter($mistyped),
                    "Substitution of '{$code[$position]}' with '{$replacement}' at position {$position} was not caught: {$mistyped}",
                );
            }
        }

        $this->assertSame(strlen($code) * (strlen(VoucherCode::ALPHABET) - 1), $checked);
    }

    /**
     * Every adjacent pair, across many codes so the value pairs involved vary.
     */
    public function test_every_adjacent_transposition_fails_the_check_character(): void
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $code = VoucherCode::generate('KAS', 10);

            for ($position = 0; $position < strlen($code) - 1; $position++) {
                if ($code[$position] === $code[$position + 1]) {
                    // Swapping identical characters produces the same code.
                    continue;
                }

                $swapped = $code;
                [$swapped[$position], $swapped[$position + 1]] = [$code[$position + 1], $code[$position]];

                $this->assertFalse(
                    VoucherCode::hasValidCheckCharacter($swapped),
                    "Transposition at position {$position} was not caught: {$code} became {$swapped}",
                );
            }
        }
    }

    public function test_the_alphabet_size_is_prime(): void
    {
        /*
         * The substitution and transposition guarantees both depend on it. If
         * someone adds a symbol back to the alphabet, this fails rather than
         * quietly degrading the checksum.
         */
        $size = strlen(VoucherCode::ALPHABET);

        $this->assertSame(31, $size);

        for ($divisor = 2; $divisor * $divisor <= $size; $divisor++) {
            $this->assertNotSame(0, $size % $divisor, "Alphabet size {$size} is divisible by {$divisor}.");
        }
    }

    public function test_the_alphabet_has_no_look_alike_characters(): void
    {
        foreach (['I', 'L', 'O', 'U'] as $excluded) {
            $this->assertStringNotContainsString(
                $excluded,
                VoucherCode::ALPHABET,
                "'{$excluded}' is too easily confused to appear in a printed code.",
            );
        }
    }

    public function test_a_code_too_long_for_one_check_character_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        VoucherCode::checkCharacter(str_repeat('A', strlen(VoucherCode::ALPHABET)));
    }

    public function test_display_form_groups_characters_for_printing(): void
    {
        $this->assertSame('KAS7-F3K9-M2QV-W5', VoucherCode::forDisplay('KAS7F3K9M2QVW5', 4));
    }

    public function test_display_form_round_trips_through_normalisation(): void
    {
        $code = VoucherCode::generate('KAS', 10);

        $this->assertSame($code, VoucherCode::normalise(VoucherCode::forDisplay($code, 4)));
    }

    public function test_prefixes_containing_folded_characters_are_rejected(): void
    {
        // I, L and O normalise to 1, 1 and 0, so a prefix using them would print
        // one string and redeem as another.
        $this->assertFalse(VoucherCode::isValidPrefix('KSI'));
        $this->assertFalse(VoucherCode::isValidPrefix('COL'));
        $this->assertFalse(VoucherCode::isValidPrefix('KAS-'));
        $this->assertFalse(VoucherCode::isValidPrefix(''));

        $this->assertTrue(VoucherCode::isValidPrefix('KAS'));
        $this->assertTrue(VoucherCode::isValidPrefix('D4R'));
    }

    public function test_suffix_returns_the_last_group_for_support_lookups(): void
    {
        $this->assertSame('QVW5', VoucherCode::suffix('KAS7F3K9M2QVW5'));
    }

    public function test_hashing_is_deterministic_across_input_formats(): void
    {
        $hasher = new VoucherCodeHasher('test-key');

        $this->assertSame(
            $hasher->hash('KAS7F3K9M2QVW5'),
            $hasher->hash('kas7-f3k9-m2qv-w5'),
        );
    }

    public function test_hashing_depends_on_the_key(): void
    {
        $code = 'KAS7F3K9M2QVW5';

        // A stolen vouchers table is useless without the separate hash key.
        $this->assertNotSame(
            (new VoucherCodeHasher('key-one'))->hash($code),
            (new VoucherCodeHasher('key-two'))->hash($code),
        );
    }

    public function test_hash_matching_rejects_a_different_code(): void
    {
        $hasher = new VoucherCodeHasher('test-key');
        $hash = $hasher->hash('KAS7F3K9M2QVW5');

        $this->assertTrue($hasher->matches('KAS7F3K9M2QVW5', $hash));
        $this->assertFalse($hasher->matches('KAS7F3K9M2QVW6', $hash));
    }
}
