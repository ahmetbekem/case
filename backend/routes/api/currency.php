<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Presentation\Currency\Controllers\CurrencyController;

// Public — no jwt.auth. Currency rates are public information; throttle
// keeps abusive scrapers out.
Route::prefix('currencies')->middleware('throttle:api-read')->group(function () {
    Route::get('/',                  [CurrencyController::class, 'index']);
    Route::get('/{base}/{target}',   [CurrencyController::class, 'show']);
});
