<?php

use App\Http\Controllers\OrderCancellationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OutboxRelayController;
use Illuminate\Support\Facades\Route;

Route::get('/', [OrderController::class, 'index'])->name('dashboard');

Route::middleware('throttle:30,1')->group(function () {
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::post('/orders/{order}/cancellations', [OrderCancellationController::class, 'store'])->name('orders.cancellations.store');
    Route::post('/outbox/relay', [OutboxRelayController::class, 'store'])->name('outbox.relay');
});
