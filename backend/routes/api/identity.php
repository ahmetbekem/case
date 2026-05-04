<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Presentation\Identity\Controllers\AuthController;
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
