<?php

namespace Tests\Feature;

use App\Contracts\MessagePublisher;
use App\Enums\OrderStatus;
use App\Enums\OutboxStatus;
use App\Models\Order;
use App\Models\OutboxMessage;
use App\RabbitMQ\FakeMessagePublisher;
use Tests\TestCase;

class PlaceOrderTest extends TestCase
{
    public function test_dashboard_renders_the_order_form(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Place an order')
            ->assertSee('Outbox');
    }

    public function test_valid_payload_creates_an_order_and_pending_outbox_row(): void
    {
        $response = $this->post(route('orders.store'), [
            'customer_email' => 'ada@example.com',
            'total' => '49.99',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('orders', [
            'customer_email' => 'ada@example.com',
            'status' => OrderStatus::Placed->value,
            'poison' => 0,
        ]);

        $order = Order::query()->first();
        $outbox = OutboxMessage::query()->first();

        $this->assertNotNull($order);
        $this->assertNotNull($outbox);
        $this->assertSame(OutboxStatus::Pending, $outbox->status);
        $this->assertSame('order.created', $outbox->routing_key);
        $this->assertSame($order->id, $outbox->payload['order_id']);
        $this->assertSame('ada@example.com', $outbox->payload['customer_email']);
        $this->assertSame('49.99', $outbox->payload['total']);
        $this->assertFalse($outbox->payload['poison']);
    }

    public function test_placing_an_order_does_not_publish_to_the_broker(): void
    {
        $this->post(route('orders.store'), [
            'customer_email' => 'ada@example.com',
            'total' => '10.00',
        ]);

        $publisher = $this->app->make(MessagePublisher::class);

        $this->assertInstanceOf(FakeMessagePublisher::class, $publisher);
        $this->assertSame([], $publisher->published);
    }

    public function test_poison_flag_is_copied_into_the_outbox_payload(): void
    {
        $this->post(route('orders.store'), [
            'customer_email' => 'poison@example.com',
            'total' => '12.00',
            'poison' => '1',
        ]);

        $outbox = OutboxMessage::query()->first();

        $this->assertNotNull($outbox);
        $this->assertTrue($outbox->payload['poison']);
        $this->assertTrue(Order::query()->first()?->poison);
    }

    public function test_rejects_store_when_required_fields_are_missing(): void
    {
        $this->from(route('dashboard'))
            ->post(route('orders.store'), [])
            ->assertRedirect(route('dashboard'))
            ->assertInvalid([
                'customer_email' => 'The customer email field is required.',
                'total' => 'The total field is required.',
            ]);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('outbox_messages', 0);
    }

    public function test_rejects_store_when_email_is_invalid(): void
    {
        $this->from(route('dashboard'))
            ->post(route('orders.store'), [
                'customer_email' => 'not-an-email',
                'total' => '10.00',
            ])
            ->assertRedirect(route('dashboard'))
            ->assertInvalid([
                'customer_email' => 'The customer email field must be a valid email address.',
            ]);
    }

    public function test_rejects_store_when_total_is_below_the_minimum(): void
    {
        $this->from(route('dashboard'))
            ->post(route('orders.store'), [
                'customer_email' => 'ada@example.com',
                'total' => '0',
            ])
            ->assertRedirect(route('dashboard'))
            ->assertInvalid([
                'total' => 'The total field must be at least 0.01.',
            ]);
    }

    public function test_cancel_writes_a_pending_order_cancelled_outbox_event(): void
    {
        $this->post(route('orders.store'), [
            'customer_email' => 'ada@example.com',
            'total' => '20.00',
        ]);

        $order = Order::query()->firstOrFail();

        $this->post(route('orders.cancellations.store', $order))
            ->assertRedirect(route('dashboard'));

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame(2, OutboxMessage::query()->count());
        $this->assertTrue(
            OutboxMessage::query()->where('routing_key', 'order.cancelled')->where('status', OutboxStatus::Pending)->exists(),
        );
    }

    public function test_cannot_cancel_an_order_that_is_already_cancelled(): void
    {
        $order = Order::factory()->cancelled()->create();

        $this->from(route('dashboard'))
            ->post(route('orders.cancellations.store', $order))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseCount('outbox_messages', 0);
    }
}
