<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reference = 'pi_'.Str::lower(Str::random(12));

        return [
            'event_id' => 'evt_'.Str::lower(Str::random(20)),
            'event_type' => 'payment.completed',
            'reference' => $reference,
            'payload' => [
                'id' => 'evt_'.Str::lower(Str::random(20)),
                'type' => 'payment.completed',
                'api_version' => '2026-01-25',
                'data' => [
                    'reference' => $reference,
                    'status' => 'completed',
                    // Snippe sends amount as an object on webhooks, unlike the
                    // plain integer accepted when creating a payment.
                    'amount' => ['value' => 1000, 'currency' => 'TZS'],
                ],
            ],
            'signature_verified' => true,
        ];
    }

    public function processed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'processed_at' => now(),
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'signature_verified' => false,
        ]);
    }
}
