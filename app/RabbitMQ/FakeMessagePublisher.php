<?php

namespace App\RabbitMQ;

use App\Contracts\MessagePublisher;
use RuntimeException;

class FakeMessagePublisher implements MessagePublisher
{
    /**
     * @var list<array{exchange: string, routing_key: string, payload: array<string, mixed>, properties: array<string, mixed>}>
     */
    public array $published = [];

    public ?RuntimeException $failure = null;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $properties
     */
    public function publish(string $exchange, string $routingKey, array $payload, array $properties = []): void
    {
        if ($this->failure instanceof RuntimeException) {
            throw $this->failure;
        }

        $this->published[] = [
            'exchange' => $exchange,
            'routing_key' => $routingKey,
            'payload' => $payload,
            'properties' => $properties,
        ];
    }
}
