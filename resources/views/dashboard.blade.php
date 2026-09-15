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
            <h2 class="text-lg font-semibold">How messages move</h2>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                HTTP never talks to RabbitMQ. The database commits first; the relay publishes later; workers ack by hand.
            </p>

            <ol class="mt-5 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                <li class="flex gap-3 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950">
                    <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-orange-600 text-xs font-semibold text-white">1</span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold">Place</p>
                        <p class="mt-0.5 text-xs text-zinc-600 dark:text-zinc-400">One transaction writes the order and a <span class="font-medium text-zinc-800 dark:text-zinc-200">pending</span> outbox row.</p>
                    </div>
                </li>
                <li class="flex gap-3 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950">
                    <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-orange-600 text-xs font-semibold text-white">2</span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold">Relay</p>
                        <p class="mt-0.5 text-xs text-zinc-600 dark:text-zinc-400">Claims pending rows and publishes to <code class="rounded bg-zinc-200 px-1 dark:bg-zinc-800">{{ config('rabbitmq.exchanges.topic') }}</code> with confirms.</p>
                    </div>
                </li>
                <li class="flex gap-3 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950">
                    <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-orange-600 text-xs font-semibold text-white">3</span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold">Route</p>
                        <p class="mt-0.5 text-xs text-zinc-600 dark:text-zinc-400"><code class="rounded bg-zinc-200 px-1 dark:bg-zinc-800">order.created</code> and <code class="rounded bg-zinc-200 px-1 dark:bg-zinc-800">order.cancelled</code> each bind to one work queue.</p>
                    </div>
                </li>
                <li class="flex gap-3 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950">
                    <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-orange-600 text-xs font-semibold text-white">4</span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold">Consume</p>
                        <p class="mt-0.5 text-xs text-zinc-600 dark:text-zinc-400">Ack on success. Inbox <code class="rounded bg-zinc-200 px-1 dark:bg-zinc-800">(message_id, queue)</code> makes redelivery safe.</p>
                    </div>
                </li>
            </ol>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <article class="rounded-lg border border-zinc-200 p-4 text-sm dark:border-zinc-800">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-orange-600">Topic</p>
                    <h3 class="mt-1 font-semibold">{{ config('rabbitmq.exchanges.topic') }}</h3>
                    <p class="mt-1 text-zinc-600 dark:text-zinc-400">Domain events. The relay never publishes to a queue name; the exchange copies each routing key to its bound work queue.</p>
                </article>
                <article class="rounded-lg border border-zinc-200 p-4 text-sm dark:border-zinc-800">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-orange-600">Direct</p>
                    <h3 class="mt-1 font-semibold">{{ config('rabbitmq.exchanges.retry') }} · {{ config('rabbitmq.exchanges.dlx') }}</h3>
                    <p class="mt-1 text-zinc-600 dark:text-zinc-400">Plumbing only. Retry is the work-queue DLX after a nack. DLX parks poison after the retry limit.</p>
                </article>
                <article class="rounded-lg border border-zinc-200 p-4 text-sm dark:border-zinc-800">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-orange-600">Manual ack</p>
                    <h3 class="mt-1 font-semibold">Prefetch {{ config('rabbitmq.prefetch_count') }}</h3>
                    <p class="mt-1 text-zinc-600 dark:text-zinc-400">Ack after the handler succeeds. <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-800">nack(requeue: false)</code> dead-letters into the TTL retry hop instead of spinning the same queue.</p>
                </article>
                <article class="rounded-lg border border-zinc-200 p-4 text-sm dark:border-zinc-800">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-orange-600">TTL + DLQ</p>
                    <h3 class="mt-1 font-semibold">{{ (int) config('rabbitmq.retry_ttl_ms') / 1000 }}s delay · {{ config('rabbitmq.max_retries') }} retries</h3>
                    <p class="mt-1 text-zinc-600 dark:text-zinc-400">Expired retry messages return to the work queue. After the limit, PHP publishes to <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-800">{{ config('rabbitmq.queues.dead') }}</code> and acks so poison stops blocking.</p>
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
