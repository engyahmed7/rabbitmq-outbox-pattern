<?php

namespace App\Console\Commands;

use App\RabbitMQ\ConnectionFactory;
use App\RabbitMQ\Topology;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('rabbitmq:setup')]
#[Description('Declare RabbitMQ exchanges, durable queues, bindings, retry topology, and the DLQ.')]
class SetupRabbitMQCommand extends Command
{
    public function handle(ConnectionFactory $connections, Topology $topology): int
    {
        try {
            $connection = $connections->make();
            $channel = $connection->channel();
            $topology->declare($channel);
            $channel->close();
            $connection->close();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('RabbitMQ topology declared.');
        $this->line('Management UI: http://'.config('rabbitmq.host').':15672');

        return self::SUCCESS;
    }
}
