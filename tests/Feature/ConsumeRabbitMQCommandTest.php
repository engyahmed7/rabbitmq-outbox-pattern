<?php

namespace Tests\Feature;

use Tests\TestCase;

class ConsumeRabbitMQCommandTest extends TestCase
{
    public function test_refuses_to_consume_when_the_broker_driver_is_fake(): void
    {
        $this->artisan('rabbitmq:consume')
            ->expectsOutput('Set RABBITMQ_DRIVER=amqp to consume from a real broker.')
            ->assertFailed();
    }

    public function test_rejects_an_unknown_queue_name(): void
    {
        config(['rabbitmq.driver' => 'amqp']);

        $this->artisan('rabbitmq:consume', ['queue' => 'not-a-queue'])
            ->expectsOutput('Unknown queue [not-a-queue].')
            ->assertFailed();
    }
}
