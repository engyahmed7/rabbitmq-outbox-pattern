<?php

namespace App\Console\Commands;

use App\RabbitMQ\MessageConsumer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('rabbitmq:consume {queue? : Queue name to consume}')]
#[Description('Consume a RabbitMQ queue with manual acknowledgements, retries, and DLQ routing.')]
class ConsumeRabbitMQCommand extends Command
{
    public function handle(MessageConsumer $consumer): int
    {
        if (config('rabbitmq.driver') !== 'amqp') {
            $this->error('Set RABBITMQ_DRIVER=amqp to consume from a real broker.');

            return self::FAILURE;
        }

        $queue = $this->argument('queue') ?? (string) config('rabbitmq.queues.orders_created');
        $allowed = array_values(config('rabbitmq.queues'));

        if (! in_array($queue, $allowed, true)) {
            $this->error("Unknown queue [{$queue}].");
            $this->line('Allowed: '.implode(', ', $allowed));

            return self::FAILURE;
        }

        $this->info("Consuming [{$queue}] with manual ack (prefetch=".config('rabbitmq.prefetch_count').').');
        $this->comment('Poison messages retry via TTL, then move to the dead-letter queue.');

        $consumer->listen($queue);

        return self::SUCCESS;
    }
}
