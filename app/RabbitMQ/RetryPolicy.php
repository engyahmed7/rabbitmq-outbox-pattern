<?php

namespace App\RabbitMQ;

use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class RetryPolicy
{
    public function shouldDeadLetter(AMQPMessage $message, string $queue): bool
    {
        return ($this->deathCount($message, $queue) + 1) > (int) config('rabbitmq.max_retries');
    }

    public function deathCount(AMQPMessage $message, string $queue): int
    {
        foreach ($this->deaths($message) as $death) {
            if (($death['queue'] ?? null) === $queue) {
                return (int) ($death['count'] ?? 0);
            }
        }

        return 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function deaths(AMQPMessage $message): array
    {
        $headers = $message->has('application_headers') ? $message->get('application_headers') : null;

        if ($headers instanceof AMQPTable) {
            $headers = $headers->getNativeData();
        }

        if (! is_array($headers)) {
            return [];
        }

        $deaths = $headers['x-death'] ?? [];

        if (! is_array($deaths)) {
            return [];
        }

        /** @var list<array<string, mixed>> $deaths */
        return array_values($deaths);
    }
}
