<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Exceptions\PoisonMessageException;
use App\Models\Order;
use App\Models\ProcessedMessage;
use Illuminate\Support\Facades\DB;

class HandleOrderCreated
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(string $messageId, string $queue, array $payload): void
    {
        if (($payload['poison'] ?? false) === true) {
            throw new PoisonMessageException("Poison OrderCreated message [{$messageId}].");
        }

        DB::transaction(function () use ($messageId, $queue, $payload): void {
            $processed = ProcessedMessage::query()->firstOrCreate(
                [
                    'message_id' => $messageId,
                    'queue' => $queue,
                ],
                [
                    'event_type' => (string) ($payload['event_type'] ?? 'order.created'),
                    'payload' => $payload,
                ],
            );

            if (! $processed->wasRecentlyCreated) {
                return;
            }

            $order = Order::query()->findOrFail($payload['order_id']);

            if ($order->status === OrderStatus::Cancelled) {
                return;
            }

            $order->update([
                'status' => OrderStatus::Confirmed,
                'processed_at' => now(),
            ]);
        });
    }
}
