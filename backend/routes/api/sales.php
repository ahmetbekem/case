<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Presentation\Sales\Controllers\CartController;
use Presentation\Sales\Controllers\OrderController;

// Every sales endpoint requires authentication. No admin-only routes —
// admins use the same APIs but get broader visibility via assertCanBeViewedBy.
Route::prefix('sales')->middleware('jwt.auth')->group(function () {

    // ─── Cart ─────────────────────────────────────────────────────
    Route::get('/cart',                       [CartController::class, 'show']);
    Route::delete('/cart',                    [CartController::class, 'clear']);
    Route::post('/cart/items',                [CartController::class, 'addItem']);
    Route::patch('/cart/items/{itemId}',      [CartController::class, 'updateItem']);
    Route::delete('/cart/items/{itemId}',     [CartController::class, 'removeItem']);

    // ─── Orders ───────────────────────────────────────────────────
    Route::get('/orders',                     [OrderController::class, 'index'])
        ->middleware('throttle:api-read');
    Route::get('/orders/{id}',                [OrderController::class, 'show']);
    Route::post('/orders',                    [OrderController::class, 'place'])
        ->middleware('throttle:order');
    Route::post('/orders/{id}/cancel',        [OrderController::class, 'cancel']);
});
