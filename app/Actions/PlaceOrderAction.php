<?php

namespace App\Actions;

use App\Enums\DomainEvent;
use App\Enums\OrderStatus;
use App\Enums\OutboxStatus;
use App\Models\Order;
use App\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;

class PlaceOrderAction
{
    /**
     * @param  array{customer_email: string, total: float|string|int, poison?: bool}  $data
     */
    public function handle(array $data): Order
    {
        return DB::transaction(function () use ($data): Order {
            $order = Order::query()->create([
                'customer_email' => $data['customer_email'],
                'total' => $data['total'],
                'status' => OrderStatus::Placed,
                'poison' => (bool) ($data['poison'] ?? false),
            ]);

            OutboxMessage::query()->create([
                'aggregate_type' => 'order',
                'aggregate_id' => (string) $order->id,
                'event_type' => DomainEvent::OrderCreated->value,
                'routing_key' => DomainEvent::OrderCreated->value,
                'payload' => [
                    'order_id' => $order->id,
                    'customer_email' => $order->customer_email,
                    'total' => (string) $order->total,
                    'poison' => $order->poison,
                ],
                'status' => OutboxStatus::Pending,
            ]);

            return $order;
        });
    }
}
