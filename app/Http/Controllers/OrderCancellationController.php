<?php

namespace App\Http\Controllers;

use App\Actions\CancelOrderAction;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class OrderCancellationController extends Controller
{
    public function store(Order $order, CancelOrderAction $cancelOrder): RedirectResponse
    {
        try {
            $cancelOrder->handle($order);
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('dashboard')
                ->withErrors(['status' => $exception->getMessage()]);
        }

        return redirect()
            ->route('dashboard')
            ->with('status', "Order #{$order->id} cancelled. A pending OrderCancelled outbox row was written.");
    }
}
