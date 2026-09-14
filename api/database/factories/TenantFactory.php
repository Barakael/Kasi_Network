<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Voucher\VoucherCode;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            /*
             * Drawn from the Crockford alphabet, since a prefix containing I, L
             * or O would be folded by code normalisation into something other
             * than what was printed.
             */
            'code_prefix' => $this->uniquePrefix(),
            'status' => 'active',
            'currency' => 'TZS',
            'timezone' => 'Africa/Dar_es_Salaam',
            'portal_name' => $name.' WiFi',
            'support_phone' => '2557'.fake()->numerify('########'),
        ];
    }

    /**
     * An operator that has connected their mobile money account and can take
     * online payments.
     */
    public function withPayments(): static
    {
        return $this->state(fn (array $attributes): array => [
            'snippe_api_key' => 'snp_test_'.Str::random(24),
            'snippe_webhook_secret' => 'whsec_'.bin2hex(random_bytes(32)),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'suspended',
        ]);
    }

    private function uniquePrefix(): string
    {
        $alphabet = VoucherCode::ALPHABET;

        do {
            $prefix = '';

            for ($i = 0; $i < 3; $i++) {
                $prefix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (Tenant::withTrashed()->where('code_prefix', $prefix)->exists());

        return $prefix;
    }
}
