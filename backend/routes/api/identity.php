<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Presentation\Identity\Controllers\AddressController;
use Presentation\Identity\Controllers\AuthController;
use Presentation\Identity\Controllers\LocationsController;
use Presentation\Identity\Controllers\UserController;

Route::prefix('auth')->group(function () {
    // Public + per-action throttle profiles from config/security.php
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('/login',    [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/refresh',  [AuthController::class, 'refresh'])->middleware('throttle:refresh');

    // Authenticated
    Route::middleware('jwt.auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',      [UserController::class, 'me'])->middleware('throttle:api-read');
    });
});

// ADR-018 — TR il/ilçe statik veri (public, dropdown'lar için)
Route::prefix('locations')->group(function () {
    Route::get('/cities',                       [LocationsController::class, 'cities']);
    Route::get('/cities/{city}/districts',      [LocationsController::class, 'districts']);
});

// ADR-018 — Adres yönetimi (jwt.auth zorunlu)
Route::prefix('identity/addresses')->middleware('jwt.auth')->group(function () {
    Route::get('/',                             [AddressController::class, 'index']);
    Route::post('/',                            [AddressController::class, 'store']);
    Route::get('/{id}',                         [AddressController::class, 'show']);
    Route::put('/{id}',                         [AddressController::class, 'update']);
    Route::delete('/{id}',                      [AddressController::class, 'destroy']);
    Route::patch('/{id}/default-delivery',      [AddressController::class, 'setDefaultDelivery']);
    Route::patch('/{id}/default-billing',       [AddressController::class, 'setDefaultBilling']);
});
