<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * ADR-017 — sales:clean-guest-carts artisan komutu yalnızca süresi
 * geçmiş aktif guest cart'ları siler. Aktif olanlar + user cart'lar +
 * non-active status'taki cart'lar (analytics) korunur.
 */
describe('sales:clean-guest-carts', function () {
    it('deletes only active guest carts whose expires_at has passed', function () {
        $expiredId = Uuid::uuid4()->toString();
        $activeGuestId = Uuid::uuid4()->toString();

        DB::table('sales.carts')->insert([
            // Süresi geçmiş aktif guest cart — silinmeli
            [
                'id' => $expiredId,
                'user_id' => null,
                'guest_session_id' => Uuid::uuid4()->toString(),
                'status' => 'active',
                'expires_at' => now()->subDays(5),
                'created_at' => now()->subDays(40),
                'updated_at' => now()->subDays(40),
            ],
            // Aktif + henüz süresi gelmemiş guest cart — kalmalı
            [
                'id' => $activeGuestId,
                'user_id' => null,
                'guest_session_id' => Uuid::uuid4()->toString(),
                'status' => 'active',
                'expires_at' => now()->addDays(20),
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(10),
            ],
        ]);

        Artisan::call('sales:clean-guest-carts');

        expect(DB::table('sales.carts')->where('id', $expiredId)->exists())->toBeFalse();
        expect(DB::table('sales.carts')->where('id', $activeGuestId)->exists())->toBeTrue();
    });

    it('preserves abandoned/converted guest carts (analytics)', function () {
        $abandonedId = Uuid::uuid4()->toString();

        DB::table('sales.carts')->insert([
            [
                'id' => $abandonedId,
                'user_id' => null,
                'guest_session_id' => Uuid::uuid4()->toString(),
                'status' => 'abandoned',
                'expires_at' => now()->subDays(60),
                'created_at' => now()->subDays(90),
                'updated_at' => now()->subDays(60),
            ],
        ]);

        Artisan::call('sales:clean-guest-carts');

        expect(DB::table('sales.carts')->where('id', $abandonedId)->exists())->toBeTrue();
    });
});
