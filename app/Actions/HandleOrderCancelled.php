<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\ProcessedMessage;
use Illuminate\Support\Facades\DB;

class HandleOrderCancelled
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(string $messageId, string $queue, array $payload): void
    {
        DB::transaction(function () use ($messageId, $queue, $payload): void {
            $processed = ProcessedMessage::query()->firstOrCreate(
                [
                    'message_id' => $messageId,
                    'queue' => $queue,
                ],
                [
                    'event_type' => (string) ($payload['event_type'] ?? 'order.cancelled'),
                    'payload' => $payload,
                ],
            );

            if (! $processed->wasRecentlyCreated) {
                return;
            }

            $order = Order::query()->findOrFail($payload['order_id']);

            $order->update([
                'status' => OrderStatus::Cancelled,
            ]);
        });
    }
}
