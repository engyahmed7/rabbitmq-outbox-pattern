<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;
use Throwable;

class BrokerUnavailableException extends RuntimeException implements ShouldntReport
{
    public static function from(Throwable $previous): self
    {
        $host = config('rabbitmq.host');
        $port = config('rabbitmq.port');

        return new self(
            "Cannot connect to RabbitMQ at {$host}:{$port}. Start the broker (`brew services start rabbitmq` or `docker compose up -d`), then run `php artisan rabbitmq:setup`.",
            0,
            $previous,
        );
    }
}
