<x-layouts.app title="Demo">
    <div class="grid gap-6 lg:grid-cols-3">
        <section class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 lg:col-span-1">
            <h2 class="text-lg font-semibold">Place an order</h2>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                One database transaction writes the order <em>and</em> a pending outbox row. Nothing is published to RabbitMQ yet.
            </p>

            <form method="POST" action="{{ route('orders.store') }}" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label for="customer_email" class="block text-sm font-medium">Customer email</label>
                    <input
                        id="customer_email"
                        name="customer_email"
                        type="email"
                        value="{{ old('customer_email', 'ada@example.com') }}"
                        required
                        class="mt-1 w-full rounded-md border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-zinc-300 outline-none focus:ring-2 focus:ring-orange-500 dark:border-zinc-700 dark:bg-zinc-950 dark:ring-zinc-700"
                    >
                </div>
                <div>
                    <label for="total" class="block text-sm font-medium">Total</label>
                    <input
                        id="total"
                        name="total"
                        type="number"
                        step="0.01"
                        min="0.01"
                        value="{{ old('total', '49.99') }}"
                        required
                        class="mt-1 w-full rounded-md border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-zinc-300 outline-none focus:ring-2 focus:ring-orange-500 dark:border-zinc-700 dark:bg-zinc-950 dark:ring-zinc-700"
                    >
                </div>
                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="poison" value="1" @checked(old('poison')) class="mt-1">
                    <span>
                        <span class="font-medium">Poison message</span>
                        <span class="block text-zinc-600 dark:text-zinc-400">The consumer will nack this event until it lands on the dead-letter queue.</span>
                    </span>
                </label>
                <button type="submit" class="w-full rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-500">
                    Save order + outbox row
                </button>
            </form>

            <form method="POST" action="{{ route('outbox.relay') }}" class="mt-3">
                @csrf
                <button type="submit" class="w-full rounded-md border border-zinc-300 px-4 py-2 text-sm font-semibold hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800">
                    Relay pending outbox to RabbitMQ
                </button>
            </form>
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 lg:col-span-2">
            <h2 class="text-lg font-semibold">How to run the demo</h2>
            <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-zinc-700 dark:text-zinc-300">
                <li><code class="rounded bg-zinc-100 px-1.5 py-0.5 dark:bg-zinc-800">docker compose up -d</code> — RabbitMQ on 5672, UI on 15672 (<code>guest</code>/<code>guest</code>)</li>
                <li><code class="rounded bg-zinc-100 px-1.5 py-0.5 dark:bg-zinc-800">php artisan migrate</code> then <code class="rounded bg-zinc-100 px-1.5 py-0.5 dark:bg-zinc-800">php artisan rabbitmq:setup</code></li>
                <li>Place an order here. Confirm a <strong>pending</strong> outbox row.</li>
                <li>Relay (button or <code class="rounded bg-zinc-100 px-1.5 py-0.5 dark:bg-zinc-800">php artisan outbox:relay --loop</code>)</li>
                <li>Consume: <code class="rounded bg-zinc-100 px-1.5 py-0.5 dark:bg-zinc-800">php artisan rabbitmq:consume</code></li>
            </ol>

            <div class="mt-6 grid gap-3 sm:grid-cols-2">
                <article class="rounded-lg bg-zinc-50 p-4 text-sm dark:bg-zinc-950">
                    <h3 class="font-semibold">Topic exchange</h3>
                    <p class="mt-1 text-zinc-600 dark:text-zinc-400">The relay publishes to <code>demo.topic</code>. <code>order.created</code> and <code>order.cancelled</code> each bind to one work queue.</p>
                </article>
                <article class="rounded-lg bg-zinc-50 p-4 text-sm dark:bg-zinc-950">
                    <h3 class="font-semibold">Queues</h3>
                    <p class="mt-1 text-zinc-600 dark:text-zinc-400">Durable classic work queues, TTL retry queues, and <code>demo.orders.dead</code>. Production can swap the same names to quorum queues.</p>
                </article>
                <article class="rounded-lg bg-zinc-50 p-4 text-sm dark:bg-zinc-950">
                    <h3 class="font-semibold">Manual ack</h3>
                    <p class="mt-1 text-zinc-600 dark:text-zinc-400">Auto-ack drops work on crash. This consumer acks after a successful handler, nacks without requeue to hit the retry TTL, then publishes to the DLQ.</p>
                </article>
                <article class="rounded-lg bg-zinc-50 p-4 text-sm dark:bg-zinc-950">
                    <h3 class="font-semibold">Dead-letter queue</h3>
                    <p class="mt-1 text-zinc-600 dark:text-zinc-400">After {{ config('rabbitmq.max_retries') }} failures, poison messages are published to <code>demo.orders.dead</code> so they stop blocking the work queue.</p>
                </article>
            </div>
        </section>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <section class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="text-lg font-semibold">Orders</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase text-zinc-500">
                        <tr>
                            <th class="pb-2">ID</th>
                            <th class="pb-2">Email</th>
                            <th class="pb-2">Status</th>
                            <th class="pb-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($orders as $order)
                            <tr>
                                <td class="py-2 font-medium">{{ $order->id }}</td>
                                <td class="py-2">{{ $order->customer_email }}</td>
                                <td class="py-2">
                                    <span @class([
                                        'rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-amber-100 text-amber-800' => $order->status->value === 'placed',
                                        'bg-emerald-100 text-emerald-800' => $order->status->value === 'confirmed',
                                        'bg-zinc-200 text-zinc-700' => $order->status->value === 'cancelled',
                                    ])>{{ $order->status->value }}</span>
                                    @if ($order->poison)
                                        <span class="ml-1 text-xs text-red-600">poison</span>
                                    @endif
                                </td>
                                <td class="py-2 text-right">
                                    @if ($order->status->value !== 'cancelled')
                                        <form method="POST" action="{{ route('orders.cancellations.store', $order) }}">
                                            @csrf
                                            <button class="text-xs font-medium text-zinc-500 hover:text-zinc-900">Cancel</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-zinc-500">No orders yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="text-lg font-semibold">Outbox</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase text-zinc-500">
                        <tr>
                            <th class="pb-2">Event</th>
                            <th class="pb-2">Status</th>
                            <th class="pb-2">Tries</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($outboxMessages as $message)
                            <tr>
                                <td class="py-2">
                                    <div class="font-medium">{{ $message->event_type }}</div>
                                    <div class="text-xs text-zinc-500">order {{ $message->aggregate_id }}</div>
                                </td>
                                <td class="py-2">{{ $message->status->value }}</td>
                                <td class="py-2">{{ $message->attempts }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-6 text-zinc-500">Outbox is empty.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="text-lg font-semibold">Processed (inbox)</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase text-zinc-500">
                        <tr>
                            <th class="pb-2">Queue</th>
                            <th class="pb-2">Event</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($processedMessages as $message)
                            <tr>
                                <td class="py-2 text-xs">{{ $message->queue }}</td>
                                <td class="py-2">{{ $message->event_type }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="py-6 text-zinc-500">Nothing consumed yet. Start <code>rabbitmq:consume</code>.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.app>
