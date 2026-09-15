<?php

namespace App\RabbitMQ;

use App\Actions\HandleOrderCancelled;
use App\Actions\HandleOrderCreated;
use App\Contracts\MessagePublisher;
use App\Models\ProcessedMessage;
use Illuminate\Support\Facades\Log;
use JsonException;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class MessageConsumer
{
    public function __construct(
        private ConnectionFactory $connections,
        private Topology $topology,
        private MessagePublisher $publisher,
        private RetryPolicy $retries,
        private HandleOrderCreated $orderCreated,
        private HandleOrderCancelled $orderCancelled,
    ) {}

    public function listen(string $queue): void
    {
        $connection = $this->connections->make();
        $channel = $connection->channel();
        $this->topology->declare($channel);

        $channel->basic_qos(0, (int) config('rabbitmq.prefetch_count'), false);
        $channel->basic_consume(
            $queue,
            '',
            false,
            false,
            false,
            false,
            fn (AMQPMessage $message) => $this->process($queue, $message),
        );

        try {
            while ($channel->is_consuming()) {
                $channel->wait();
            }
        } finally {
            if ($channel->is_open()) {
                $channel->close();
            }

            $connection->close();
        }
    }

    public function process(string $queue, AMQPMessage $message): void
    {
        try {
            $payload = $this->decode($message);
            $messageId = $this->messageId($message, $payload);

            $this->dispatch($queue, $messageId, $payload);
            $message->ack();
        } catch (Throwable $exception) {
            $this->handleFailure($queue, $message, $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatch(string $queue, string $messageId, array $payload): void
    {
        match ($queue) {
            (string) config('rabbitmq.queues.orders_created') => $this->orderCreated->handle($messageId, $queue, $payload),
            (string) config('rabbitmq.queues.orders_cancelled') => $this->orderCancelled->handle($messageId, $queue, $payload),
            default => $this->record($messageId, $queue, $payload),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function record(string $messageId, string $queue, array $payload): void
    {
        ProcessedMessage::query()->firstOrCreate(
            [
                'message_id' => $messageId,
                'queue' => $queue,
            ],
            [
                'event_type' => (string) ($payload['event_type'] ?? $queue),
                'payload' => $payload,
            ],
        );
    }

    private function handleFailure(string $queue, AMQPMessage $message, Throwable $exception): void
    {
        Log::warning('RabbitMQ consumer failed to process a message.', [
            'queue' => $queue,
            'message_id' => $message->has('message_id') ? $message->get('message_id') : null,
            'exception' => $exception->getMessage(),
        ]);

        if ($this->retries->shouldDeadLetter($message, $queue)) {
            $this->deadLetter($queue, $message, $exception);
            $message->ack();

            return;
        }

        $message->nack(requeue: false);
    }

    private function deadLetter(string $queue, AMQPMessage $message, Throwable $exception): void
    {
        $payload = $this->decode($message);

        $this->publisher->publish(
            (string) config('rabbitmq.exchanges.dlx'),
            $this->deadLetterRoutingKey($queue),
            $payload,
            [
                'message_id' => $this->messageId($message, $payload),
                'type' => $message->has('type') ? $message->get('type') : 'dead-lettered',
                'headers' => [
                    'x-original-queue' => $queue,
                    'x-exception' => $exception->getMessage(),
                ],
            ],
        );
    }

    private function deadLetterRoutingKey(string $queue): string
    {
        return match ($queue) {
            (string) config('rabbitmq.queues.orders_cancelled') => 'order.cancelled.dead',
            default => 'order.created.dead',
        };
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function decode(AMQPMessage $message): array
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function messageId(AMQPMessage $message, array $payload): string
    {
        if ($message->has('message_id')) {
            return (string) $message->get('message_id');
        }

        return (string) ($payload['event_id'] ?? $payload['order_id'] ?? spl_object_hash($message));
    }
}
