<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Voucher\VoucherCode;
use App\Domain\Voucher\VoucherCodeHasher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class VoucherCodeTest extends TestCase
{
    public function test_generated_code_is_body_plus_check_character(): void
    {
        $code = VoucherCode::generate(9);

        $this->assertSame(10, strlen($code));
    }

    public function test_generated_codes_pass_their_own_check_character(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $code = VoucherCode::generate(9);

            $this->assertTrue(
                VoucherCode::hasValidCheckCharacter($code),
                "Generated code failed its own checksum: {$code}",
            );
        }
    }

    public function test_generated_codes_only_use_the_crockford_alphabet(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $code = VoucherCode::generate(9);

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
            $codes[] = VoucherCode::generate(9);
        }

        $this->assertCount(500, array_unique($codes));
    }

    public function test_generated_codes_do_not_share_a_fixed_prefix(): void
    {
        $codes = [];

        for ($i = 0; $i < 40; $i++) {
            $codes[] = VoucherCode::generate(9);
        }

        $prefixes = array_unique(array_map(fn (string $c): string => substr($c, 0, 3), $codes));

        $this->assertGreaterThan(1, count($prefixes), 'Codes must not all start with the same three characters.');
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
            'lowercase' => ['7f3k9m2qv5', '7F3K9M2QV5'],
            'dashes' => ['7F3K9-M2QV5', '7F3K9M2QV5'],
            'spaces' => ['7F3K9 M2QV5', '7F3K9M2QV5'],
            'surrounding whitespace' => ['  7F3K9M2QV5  ', '7F3K9M2QV5'],
            'letter O typed for zero' => ['OABC', '0ABC'],
            'letter I typed for one' => ['IABC', '1ABC'],
            'letter L typed for one' => ['LABC', '1ABC'],
        ];
    }

    public function test_every_single_character_substitution_fails_the_check_character(): void
    {
        $code = VoucherCode::generate(9);
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

    public function test_every_adjacent_transposition_fails_the_check_character(): void
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $code = VoucherCode::generate(9);

            for ($position = 0; $position < strlen($code) - 1; $position++) {
                if ($code[$position] === $code[$position + 1]) {
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
        $this->assertSame('7F3K9-M2QV5', VoucherCode::forDisplay('7F3K9M2QV5', 5));
    }

    public function test_display_form_round_trips_through_normalisation(): void
    {
        $code = VoucherCode::generate(9);

        $this->assertSame($code, VoucherCode::normalise(VoucherCode::forDisplay($code, 5)));
    }

    public function test_prefixes_containing_folded_characters_are_rejected(): void
    {
        $this->assertFalse(VoucherCode::isValidPrefix('KSI'));
        $this->assertFalse(VoucherCode::isValidPrefix('COL'));
        $this->assertFalse(VoucherCode::isValidPrefix('KAS-'));
        $this->assertFalse(VoucherCode::isValidPrefix(''));

        $this->assertTrue(VoucherCode::isValidPrefix('KAS'));
        $this->assertTrue(VoucherCode::isValidPrefix('D4R'));
    }

    public function test_suffix_returns_the_last_group_for_support_lookups(): void
    {
        $this->assertSame('M2QV5', VoucherCode::suffix('7F3K9M2QV5', 5));
    }

    public function test_hashing_is_deterministic_across_input_formats(): void
    {
        $hasher = new VoucherCodeHasher('test-key');

        $this->assertSame(
            $hasher->hash('7F3K9M2QV5'),
            $hasher->hash('7f3k9-m2qv5'),
        );
    }

    public function test_hashing_depends_on_the_key(): void
    {
        $code = '7F3K9M2QV5';

        $this->assertNotSame(
            (new VoucherCodeHasher('key-one'))->hash($code),
            (new VoucherCodeHasher('key-two'))->hash($code),
        );
    }

    public function test_hash_matching_rejects_a_different_code(): void
    {
        $hasher = new VoucherCodeHasher('test-key');
        $hash = $hasher->hash('7F3K9M2QV5');

        $this->assertTrue($hasher->matches('7F3K9M2QV5', $hash));
        $this->assertFalse($hasher->matches('7F3K9M2QV6', $hash));
    }
}
