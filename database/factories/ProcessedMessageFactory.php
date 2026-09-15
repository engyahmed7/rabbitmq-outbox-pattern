<?php

namespace Database\Factories;

use App\Enums\DomainEvent;
use App\Models\ProcessedMessage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProcessedMessage>
 */
class ProcessedMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_id' => (string) Str::uuid(),
            'queue' => 'demo.orders.created',
            'event_type' => DomainEvent::OrderCreated->value,
            'payload' => [
                'order_id' => fake()->numberBetween(1, 99),
            ],
        ];
    }
}
