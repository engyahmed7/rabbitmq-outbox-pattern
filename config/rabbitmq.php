<?php

return [

    'driver' => env('RABBITMQ_DRIVER', 'amqp'),

    'host' => env('RABBITMQ_HOST', '127.0.0.1'),
    'port' => env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),
    'vhost' => env('RABBITMQ_VHOST', '/'),

    'connection_timeout' => (float) env('RABBITMQ_CONNECTION_TIMEOUT', 3.0),
    'read_write_timeout' => (float) env('RABBITMQ_READ_WRITE_TIMEOUT', 10.0),
    'heartbeat' => (int) env('RABBITMQ_HEARTBEAT', 30),
    'confirm_timeout' => (int) env('RABBITMQ_CONFIRM_TIMEOUT', 5),

    'prefetch_count' => (int) env('RABBITMQ_PREFETCH', 1),
    'max_retries' => (int) env('RABBITMQ_MAX_RETRIES', 3),
    'retry_ttl_ms' => (int) env('RABBITMQ_RETRY_TTL_MS', 5000),
    'relay_batch_size' => (int) env('OUTBOX_RELAY_BATCH', 50),
    'publish_max_attempts' => (int) env('OUTBOX_PUBLISH_MAX_ATTEMPTS', 10),
    'reclaim_after_seconds' => (int) env('OUTBOX_RECLAIM_AFTER', 120),

    'exchanges' => [
        'topic' => 'demo.topic',
        'retry' => 'demo.retry',
        'dlx' => 'demo.dlx',
    ],

    'queues' => [
        'orders_created' => 'demo.orders.created',
        'orders_created_retry' => 'demo.orders.created.retry',
        'orders_cancelled' => 'demo.orders.cancelled',
        'orders_cancelled_retry' => 'demo.orders.cancelled.retry',
        'dead' => 'demo.orders.dead',
    ],

];
