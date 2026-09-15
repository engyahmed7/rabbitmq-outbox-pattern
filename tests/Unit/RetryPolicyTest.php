<?php

namespace Tests\Unit;

use App\RabbitMQ\RetryPolicy;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Tests\TestCase;

class RetryPolicyTest extends TestCase
{
    public function test_does_not_dead_letter_on_the_first_failure(): void
    {
        $policy = new RetryPolicy;
        $message = new AMQPMessage('{}');

        $this->assertFalse($policy->shouldDeadLetter($message, 'demo.orders.created'));
        $this->assertSame(0, $policy->deathCount($message, 'demo.orders.created'));
    }

    public function test_dead_letters_after_the_configured_retry_limit(): void
    {
        config(['rabbitmq.max_retries' => 3]);

        $policy = new RetryPolicy;
        $message = new AMQPMessage('{}', [
            'application_headers' => new AMQPTable([
                'x-death' => [
                    ['queue' => 'demo.orders.created', 'count' => 3],
                ],
            ]),
        ]);

        $this->assertTrue($policy->shouldDeadLetter($message, 'demo.orders.created'));
        $this->assertSame(3, $policy->deathCount($message, 'demo.orders.created'));
    }
}
