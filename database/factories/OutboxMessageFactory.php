<?php

namespace Database\Factories;

use App\Enums\DomainEvent;
use App\Enums\OutboxStatus;
use App\Models\OutboxMessage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OutboxMessage>
 */
class OutboxMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $orderId = (string) fake()->numberBetween(1, 9999);

        return [
            'uuid' => (string) Str::uuid(),
            'aggregate_type' => 'order',
            'aggregate_id' => $orderId,
            'event_type' => DomainEvent::OrderCreated->value,
            'routing_key' => DomainEvent::OrderCreated->value,
            'payload' => [
                'order_id' => (int) $orderId,
                'customer_email' => fake()->safeEmail(),
                'total' => '42.00',
                'poison' => false,
            ],
            'status' => OutboxStatus::Pending,
            'attempts' => 0,
            'published_at' => null,
            'last_error' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OutboxStatus::Published,
            'published_at' => now(),
            'attempts' => 1,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OutboxStatus::Failed,
            'attempts' => 10,
            'last_error' => 'broker unavailable',
        ]);
    }
}
