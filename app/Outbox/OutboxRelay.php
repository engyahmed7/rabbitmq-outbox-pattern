<?php

namespace App\Outbox;

use App\Contracts\MessagePublisher;
use App\Enums\OutboxStatus;
use App\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class OutboxRelay
{
    public function __construct(private MessagePublisher $publisher) {}

    public function handle(?int $limit = null): int
    {
        $this->reclaimStuckPublishing();

        $limit ??= (int) config('rabbitmq.relay_batch_size');
        $published = 0;

        $ids = OutboxMessage::query()
            ->pending()
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($ids as $id) {
            if ($this->publishOne((int) $id)) {
                $published++;
            }
        }

        return $published;
    }

    private function reclaimStuckPublishing(): void
    {
        OutboxMessage::query()
            ->where('status', OutboxStatus::Publishing)
            ->where('updated_at', '<=', now()->subSeconds((int) config('rabbitmq.reclaim_after_seconds')))
            ->update(['status' => OutboxStatus::Pending]);
    }

    private function publishOne(int $id): bool
    {
        $claimed = OutboxMessage::query()
            ->whereKey($id)
            ->where('status', OutboxStatus::Pending)
            ->update([
                'status' => OutboxStatus::Publishing,
                'attempts' => DB::raw('attempts + 1'),
            ]);

        if ($claimed !== 1) {
            return false;
        }

        $message = OutboxMessage::query()->find($id);

        if ($message === null) {
            return false;
        }

        try {
            $this->publisher->publish(
                (string) config('rabbitmq.exchanges.topic'),
                $message->routing_key,
                $message->payload,
                [
                    'message_id' => $message->uuid,
                    'type' => $message->event_type,
                ],
            );

            $message->update([
                'status' => OutboxStatus::Published,
                'published_at' => now(),
                'last_error' => null,
            ]);

            return true;
        } catch (Throwable $exception) {
            $failed = $message->attempts >= (int) config('rabbitmq.publish_max_attempts');

            $message->update([
                'status' => $failed ? OutboxStatus::Failed : OutboxStatus::Pending,
                'last_error' => Str::limit($exception->getMessage(), 1000),
            ]);

            report($exception);

            return false;
        }
    }
}
