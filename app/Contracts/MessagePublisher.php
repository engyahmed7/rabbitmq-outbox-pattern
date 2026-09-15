<?php

namespace App\Contracts;

interface MessagePublisher
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $properties
     */
    public function publish(string $exchange, string $routingKey, array $payload, array $properties = []): void;
}
