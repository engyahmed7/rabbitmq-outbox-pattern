<?php

namespace App\Console\Commands;

use App\Outbox\OutboxRelay;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Sleep;

#[Signature('outbox:relay {--loop : Keep polling for pending outbox rows} {--sleep=1 : Seconds to wait between loop iterations}')]
#[Description('Publish pending outbox rows to RabbitMQ with publisher confirms.')]
class RelayOutboxCommand extends Command
{
    public function handle(OutboxRelay $relay): int
    {
        do {
            $published = $relay->handle();

            if ($published > 0) {
                $this->info("Published {$published} outbox message(s).");
            } elseif (! $this->option('loop')) {
                $this->comment('No pending outbox messages.');
            }

            if ($this->option('loop')) {
                Sleep::for((int) $this->option('sleep'))->seconds();
            }
        } while ($this->option('loop'));

        return self::SUCCESS;
    }
}
