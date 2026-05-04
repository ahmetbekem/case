<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Presentation\Identity\Controllers\AuthController;
use Presentation\Identity\Controllers\UserController;

Route::prefix('auth')->group(function () {
    // Public — no jwt.auth middleware
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
    Route::post('/refresh',  [AuthController::class, 'refresh']);

    // Authenticated
    Route::middleware('jwt.auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',      [UserController::class, 'me']);
    });
});
