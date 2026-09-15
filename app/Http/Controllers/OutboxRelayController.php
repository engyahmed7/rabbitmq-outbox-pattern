<?php

namespace App\Http\Controllers;

use App\Outbox\OutboxRelay;
use Illuminate\Http\RedirectResponse;
use Throwable;

class OutboxRelayController extends Controller
{
    public function store(OutboxRelay $relay): RedirectResponse
    {
        try {
            $published = $relay->handle();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('dashboard')
                ->withErrors(['status' => 'Relay failed: '.$exception->getMessage()]);
        }

        $message = $published === 0
            ? 'No pending outbox messages to publish.'
            : "Published {$published} outbox message(s) to RabbitMQ.";

        return redirect()
            ->route('dashboard')
            ->with('status', $message);
    }
}
