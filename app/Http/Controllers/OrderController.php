<?php

namespace App\Http\Controllers;

use App\Actions\PlaceOrderAction;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\OutboxMessage;
use App\Models\ProcessedMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(): View
    {
        return view('dashboard', [
            'orders' => Order::query()->latest('id')->limit(25)->get(),
            'outboxMessages' => OutboxMessage::query()->latest('id')->limit(25)->get(),
            'processedMessages' => ProcessedMessage::query()->latest('id')->limit(25)->get(),
        ]);
    }

    public function store(StoreOrderRequest $request, PlaceOrderAction $placeOrder): RedirectResponse
    {
        $order = $placeOrder->handle($request->safe()->only(['customer_email', 'total', 'poison']));

        return redirect()
            ->route('dashboard')
            ->with('status', "Order #{$order->id} saved with a pending outbox event. Relay it to RabbitMQ next.");
    }
}
