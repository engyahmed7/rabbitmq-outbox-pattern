<?php

namespace Tests\Feature;

use App\Actions\HandleOrderCancelled;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Str;
use Tests\TestCase;

class HandleOrderCancelledTest extends TestCase
{
    public function test_marks_a_confirmed_order_as_cancelled(): void
    {
        $order = Order::factory()->confirmed()->create();

        $this->app->make(HandleOrderCancelled::class)->handle((string) Str::uuid(), 'demo.orders.cancelled', [
            'order_id' => $order->id,
            'event_type' => 'order.cancelled',
        ]);

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertDatabaseHas('processed_messages', [
            'queue' => 'demo.orders.cancelled',
            'event_type' => 'order.cancelled',
        ]);
    }
}
