<?php

namespace App\RabbitMQ;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Wire\AMQPTable;

class Topology
{
    public function declare(AMQPChannel $channel): void
    {
        $this->declareExchanges($channel);
        $this->declareWorkQueues($channel);
        $this->bindQueues($channel);
    }

    private function declareExchanges(AMQPChannel $channel): void
    {
        $durable = true;

        $channel->exchange_declare((string) config('rabbitmq.exchanges.topic'), AMQPExchangeType::TOPIC, false, $durable, false);
        $channel->exchange_declare((string) config('rabbitmq.exchanges.retry'), AMQPExchangeType::DIRECT, false, $durable, false);
        $channel->exchange_declare((string) config('rabbitmq.exchanges.dlx'), AMQPExchangeType::DIRECT, false, $durable, false);
    }

    private function declareWorkQueues(AMQPChannel $channel): void
    {
        $this->declareDurableQueue($channel, (string) config('rabbitmq.queues.orders_created'), new AMQPTable([
            'x-dead-letter-exchange' => (string) config('rabbitmq.exchanges.retry'),
            'x-dead-letter-routing-key' => 'order.created.retry',
        ]));

        $this->declareDurableQueue($channel, (string) config('rabbitmq.queues.orders_created_retry'), new AMQPTable([
            'x-message-ttl' => (int) config('rabbitmq.retry_ttl_ms'),
            'x-dead-letter-exchange' => '',
            'x-dead-letter-routing-key' => (string) config('rabbitmq.queues.orders_created'),
        ]));

        $this->declareDurableQueue($channel, (string) config('rabbitmq.queues.orders_cancelled'), new AMQPTable([
            'x-dead-letter-exchange' => (string) config('rabbitmq.exchanges.retry'),
            'x-dead-letter-routing-key' => 'order.cancelled.retry',
        ]));

        $this->declareDurableQueue($channel, (string) config('rabbitmq.queues.orders_cancelled_retry'), new AMQPTable([
            'x-message-ttl' => (int) config('rabbitmq.retry_ttl_ms'),
            'x-dead-letter-exchange' => '',
            'x-dead-letter-routing-key' => (string) config('rabbitmq.queues.orders_cancelled'),
        ]));

        $this->declareDurableQueue($channel, (string) config('rabbitmq.queues.dead'));
    }

    private function bindQueues(AMQPChannel $channel): void
    {
        $topic = (string) config('rabbitmq.exchanges.topic');
        $retry = (string) config('rabbitmq.exchanges.retry');
        $dlx = (string) config('rabbitmq.exchanges.dlx');

        $channel->queue_bind((string) config('rabbitmq.queues.orders_created'), $topic, 'order.created');
        $channel->queue_bind((string) config('rabbitmq.queues.orders_cancelled'), $topic, 'order.cancelled');

        $channel->queue_bind((string) config('rabbitmq.queues.orders_created_retry'), $retry, 'order.created.retry');
        $channel->queue_bind((string) config('rabbitmq.queues.orders_cancelled_retry'), $retry, 'order.cancelled.retry');
        $channel->queue_bind((string) config('rabbitmq.queues.dead'), $dlx, 'order.created.dead');
        $channel->queue_bind((string) config('rabbitmq.queues.dead'), $dlx, 'order.cancelled.dead');
    }

    private function declareDurableQueue(AMQPChannel $channel, string $name, ?AMQPTable $arguments = null): void
    {
        $channel->queue_declare(
            $name,
            false,
            true,
            false,
            false,
            false,
            $arguments ?? new AMQPTable,
        );
    }
}
