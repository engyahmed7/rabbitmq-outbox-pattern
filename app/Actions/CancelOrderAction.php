<?php

namespace App\Actions;

use App\Enums\DomainEvent;
use App\Enums\OrderStatus;
use App\Enums\OutboxStatus;
use App\Models\Order;
use App\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CancelOrderAction
{
    public function handle(Order $order): Order
    {
        if ($order->status === OrderStatus::Cancelled) {
            throw new InvalidArgumentException('This order is already cancelled.');
        }

        return DB::transaction(function () use ($order): Order {
            $order->update([
                'status' => OrderStatus::Cancelled,
            ]);

            OutboxMessage::query()->create([
                'aggregate_type' => 'order',
                'aggregate_id' => (string) $order->id,
                'event_type' => DomainEvent::OrderCancelled->value,
                'routing_key' => DomainEvent::OrderCancelled->value,
                'payload' => [
                    'order_id' => $order->id,
                    'customer_email' => $order->customer_email,
                    'total' => (string) $order->total,
                    'poison' => false,
                ],
                'status' => OutboxStatus::Pending,
            ]);

            return $order->refresh();
        });
    }
}
