<?php

namespace Tests\Feature;

use App\Actions\HandleOrderCreated;
use App\Enums\OrderStatus;
use App\Exceptions\PoisonMessageException;
use App\Models\Order;
use App\Models\ProcessedMessage;
use Illuminate\Support\Str;
use Tests\TestCase;

class HandleOrderCreatedTest extends TestCase
{
    public function test_confirms_the_order_and_records_a_processed_message(): void
    {
        $this->travelTo('2026-09-14 12:00:00');

        $order = Order::factory()->create();
        $messageId = (string) Str::uuid();

        $this->app->make(HandleOrderCreated::class)->handle($messageId, 'demo.orders.created', [
            'order_id' => $order->id,
            'customer_email' => $order->customer_email,
            'total' => (string) $order->total,
            'poison' => false,
            'event_type' => 'order.created',
        ]);

        $order->refresh();
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertSame('2026-09-14 12:00:00', $order->processed_at?->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('processed_messages', [
            'message_id' => $messageId,
            'queue' => 'demo.orders.created',
        ]);
    }

    public function test_is_idempotent_for_a_duplicate_message_id(): void
    {
        $order = Order::factory()->create();
        $messageId = (string) Str::uuid();
        $handler = $this->app->make(HandleOrderCreated::class);
        $payload = [
            'order_id' => $order->id,
            'poison' => false,
            'event_type' => 'order.created',
        ];

        $handler->handle($messageId, 'demo.orders.created', $payload);
        $firstProcessedAt = $order->refresh()->processed_at;

        $this->travel(5)->minutes();
        $handler->handle($messageId, 'demo.orders.created', $payload);

        $this->assertTrue($order->refresh()->processed_at?->equalTo($firstProcessedAt));
        $this->assertSame(1, ProcessedMessage::query()->count());
    }

    public function test_throws_for_a_poison_payload_without_confirming_the_order(): void
    {
        $order = Order::factory()->poisoned()->create();

        try {
            $this->app->make(HandleOrderCreated::class)->handle((string) Str::uuid(), 'demo.orders.created', [
                'order_id' => $order->id,
                'poison' => true,
            ]);
            $this->fail('Poison payloads must throw.');
        } catch (PoisonMessageException) {
            $order->refresh();
            $this->assertSame(OrderStatus::Placed, $order->status);
            $this->assertNull($order->processed_at);
            $this->assertDatabaseCount('processed_messages', 0);
        }
    }

    public function test_does_not_confirm_an_order_that_was_already_cancelled(): void
    {
        $order = Order::factory()->cancelled()->create();

        $this->app->make(HandleOrderCreated::class)->handle((string) Str::uuid(), 'demo.orders.created', [
            'order_id' => $order->id,
            'poison' => false,
        ]);

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertDatabaseCount('processed_messages', 1);
    }
}
