<?php

namespace App\RabbitMQ;

use App\Contracts\MessagePublisher;
use JsonException;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use RuntimeException;

class RabbitMQPublisher implements MessagePublisher
{
    private ?AMQPStreamConnection $connection = null;

    private ?AMQPChannel $channel = null;

    public function __construct(
        private ConnectionFactory $connections,
        private Topology $topology,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $properties
     *
     * @throws JsonException
     */
    public function publish(string $exchange, string $routingKey, array $payload, array $properties = []): void
    {
        $headers = $properties['headers'] ?? [];
        unset($properties['headers']);

        $message = new AMQPMessage(json_encode($payload, JSON_THROW_ON_ERROR), array_merge([
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'timestamp' => time(),
            'app_id' => (string) config('app.name'),
            'application_headers' => new AMQPTable(is_array($headers) ? $headers : []),
        ], $properties));

        $channel = $this->channel();
        $channel->basic_publish($message, $exchange, $routingKey);
        $channel->wait_for_pending_acks_returns((int) config('rabbitmq.confirm_timeout'));
    }

    public function close(): void
    {
        if ($this->channel?->is_open()) {
            $this->channel->close();
        }

        $this->connection?->close();

        $this->channel = null;
        $this->connection = null;
    }

    public function __destruct()
    {
        $this->close();
    }

    private function channel(): AMQPChannel
    {
        if ($this->channel?->is_open()) {
            return $this->channel;
        }

        $this->connection = $this->connections->make();
        $this->channel = $this->connection->channel();

        if (! $this->channel instanceof AMQPChannel) {
            throw new RuntimeException('Unable to open a RabbitMQ channel.');
        }

        $this->topology->declare($this->channel);
        $this->channel->confirm_select();

        return $this->channel;
    }
}
