<?php

namespace Tests\Feature;

use App\Contracts\MessagePublisher;
use App\Enums\OutboxStatus;
use App\Models\OutboxMessage;
use App\Outbox\OutboxRelay;
use App\RabbitMQ\FakeMessagePublisher;
use RuntimeException;
use Tests\TestCase;

class RelayOutboxTest extends TestCase
{
    public function test_relay_publishes_pending_messages_and_marks_them_published(): void
    {
        $message = OutboxMessage::factory()->create();

        $published = $this->app->make(OutboxRelay::class)->handle();

        $this->assertSame(1, $published);

        $message->refresh();
        $this->assertSame(OutboxStatus::Published, $message->status);
        $this->assertNotNull($message->published_at);
        $this->assertSame(1, $message->attempts);

        $publisher = $this->app->make(MessagePublisher::class);
        $this->assertInstanceOf(FakeMessagePublisher::class, $publisher);
        $this->assertCount(1, $publisher->published);
        $this->assertSame('demo.topic', $publisher->published[0]['exchange']);
        $this->assertSame('order.created', $publisher->published[0]['routing_key']);
        $this->assertSame($message->uuid, $publisher->published[0]['properties']['message_id']);
        $this->assertSame($message->payload, $publisher->published[0]['payload']);
    }

    public function test_relay_returns_a_message_to_pending_when_publish_fails(): void
    {
        $message = OutboxMessage::factory()->create();
        $fake = $this->app->make(FakeMessagePublisher::class);
        $fake->failure = new RuntimeException('broker down');

        $published = $this->app->make(OutboxRelay::class)->handle();

        $this->assertSame(0, $published);

        $message->refresh();
        $this->assertSame(OutboxStatus::Pending, $message->status);
        $this->assertSame(1, $message->attempts);
        $this->assertSame('broker down', $message->last_error);
    }

    public function test_relay_marks_a_message_failed_after_the_publish_attempt_limit(): void
    {
        config(['rabbitmq.publish_max_attempts' => 1]);

        $message = OutboxMessage::factory()->create();
        $fake = $this->app->make(FakeMessagePublisher::class);
        $fake->failure = new RuntimeException('broker down');

        $this->app->make(OutboxRelay::class)->handle();

        $message->refresh();
        $this->assertSame(OutboxStatus::Failed, $message->status);
        $this->assertSame(1, $message->attempts);
    }

    public function test_http_relay_endpoint_publishes_pending_rows(): void
    {
        OutboxMessage::factory()->create();

        $this->post(route('outbox.relay'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status');

        $this->assertSame(OutboxStatus::Published, OutboxMessage::query()->first()?->status);
    }
}
