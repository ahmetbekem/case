<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Presentation\Sales\Controllers\CartController;
use Presentation\Sales\Controllers\OrderController;

Route::prefix('sales')->group(function () {

    // ─── Cart ─────────────────────────────────────────────────────
    // ADR-017 — Cart hem authenticated user hem de guest tarafından
    // erişilebilir. optional.jwt token varsa user_id attribute'u set
    // eder; guest.session yoksa cookie üretir + guest_session_id
    // attribute'u set eder. Sıralama önemli: önce optional.jwt ki
    // GuestSessionMiddleware login-li request'te cookie üretmesin.
    Route::middleware(['optional.jwt', 'guest.session'])->group(function () {
        Route::get('/cart',                       [CartController::class, 'show']);
        Route::delete('/cart',                    [CartController::class, 'clear']);
        Route::post('/cart/items',                [CartController::class, 'addItem']);
        Route::patch('/cart/items/{itemId}',      [CartController::class, 'updateItem']);
        Route::delete('/cart/items/{itemId}',     [CartController::class, 'removeItem']);
    });

    // ─── Orders ───────────────────────────────────────────────────
    // Checkout zorunlu auth — guest order yok (KVKK denetim).
    Route::middleware('jwt.auth')->group(function () {
        Route::get('/orders',                     [OrderController::class, 'index'])
            ->middleware('throttle:api-read');
        Route::get('/orders/{id}',                [OrderController::class, 'show']);
        Route::post('/orders',                    [OrderController::class, 'place'])
            ->middleware('throttle:order');
        Route::post('/orders/{id}/cancel',        [OrderController::class, 'cancel']);
    });
});
