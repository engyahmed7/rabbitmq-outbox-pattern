<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_email' => fake()->unique()->safeEmail(),
            'total' => fake()->randomFloat(2, 10, 500),
            'status' => OrderStatus::Placed,
            'poison' => false,
            'processed_at' => null,
        ];
    }

    public function poisoned(): static
    {
        return $this->state(fn (array $attributes): array => [
            'poison' => true,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Confirmed,
            'processed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Cancelled,
        ]);
    }
}
