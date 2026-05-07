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

// ADR-017 — Süresi geçmiş guest cart'ları her gece siler. 03:00 trafik
// düşük; cleanup CASCADE ile cart_items'ı da temizler. Lock ile
// concurrent run engellenir, multi-server'da yalnızca bir node alır.
Schedule::command('sales:clean-guest-carts')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onOneServer();

// Code Review v2 KRİTİK Fix — Daily DB backup (pg_dump | gzip).
// 02:00'de çalışır (cleanup'tan önce — temiz state'i yakala). Script
// 30 günlük rolling daily + 12 aylık monthly retention uygular.
// Çalışma yolu: container içinden değil, host'tan exec ediyoruz
// (docker compose exec postgres pg_dump). Bu yüzden Schedule::exec
// kullanıyoruz.
Schedule::exec('bash /var/www/scripts/backup-db.sh')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->onSuccess(function () {
        \Log::info('DB backup completed successfully');
    })
    ->onFailure(function () {
        \Log::error('DB backup FAILED — investigate immediately');
    });
