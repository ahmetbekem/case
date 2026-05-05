<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily currency rate sync (TASK 0.12). Frankfurter publishes ECB rates
// around 16:00 CET; running at 06:00 local picks up the previous day's
// close and lets us amortise occasional API hiccups before peak hours.
Schedule::command('currency:sync-rates')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->onOneServer();
