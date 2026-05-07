<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * ADR-018 — Address yönetimi e2e: CRUD + IDOR + limit + il/ilçe
 * validation + default re-pick.
 */

function postAddress(string $token, array $payload = []): \Illuminate\Testing\TestResponse
{
    $defaults = [
        'title' => 'Ev',
        'fullName' => 'Test User',
        'phone' => '+905551234567',
        'city' => 'İstanbul',
        'district' => 'Kadıköy',
        'addressLine' => 'Moda Caddesi No: 1 Daire: 5',
    ];
    return test()->postJson(
        '/api/v1/identity/addresses',
        array_merge($defaults, $payload),
        ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'],
    );
}

describe('POST /identity/addresses', function () {
    it('creates an address and returns AddressDTO', function () {
        $session = registerAndLoginCustomer('addr-create@test.local');

        $response = postAddress($session['accessToken'], ['title' => 'Yeni Ev'])
            ->assertCreated();

        expect($response->json('data.title'))->toBe('Yeni Ev')
            ->and($response->json('data.city'))->toBe('İstanbul')
            ->and($response->json('data.phone'))->toBe('+905551234567')
            ->and($response->json('data.phoneDisplay'))->toBe('0 555 123 45 67')
            ->and($response->json('data.countryCode'))->toBe('TR')
            ->and($response->json('message'))->toBe('Address created');
    });

    it('first address auto-becomes default delivery + billing', function () {
        $session = registerAndLoginCustomer('addr-first@test.local');

        $response = postAddress($session['accessToken'])->assertCreated();

        expect($response->json('data.isDefaultDelivery'))->toBeTrue()
            ->and($response->json('data.isDefaultBilling'))->toBeTrue();
    });

    it('rejects invalid TR city with 422 INVALID_CITY', function () {
        $session = registerAndLoginCustomer('addr-bad-city@test.local');

        postAddress($session['accessToken'], ['city' => 'Atlantis'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_CITY');
    });

    it('rejects district not in city with 422 INVALID_DISTRICT', function () {
        $session = registerAndLoginCustomer('addr-bad-district@test.local');

        postAddress($session['accessToken'], ['city' => 'İstanbul', 'district' => 'Çankaya'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_DISTRICT');
    });

    it('rejects 11th address with 409 ADDRESS_LIMIT_REACHED', function () {
        $session = registerAndLoginCustomer('addr-limit@test.local');
        for ($i = 1; $i <= 10; $i++) {
            postAddress($session['accessToken'], ['title' => "A{$i}"])->assertCreated();
        }
        postAddress($session['accessToken'], ['title' => 'A11'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'ADDRESS_LIMIT_REACHED');
    });

    it('setAsDefaultDelivery=true unsets old default', function () {
        $session = registerAndLoginCustomer('addr-set-default@test.local');
        $first = postAddress($session['accessToken'], ['title' => 'Adres-A'])->assertCreated();
        expect($first->json('data.isDefaultDelivery'))->toBeTrue();

        $second = postAddress($session['accessToken'], [
            'title' => 'Adres-B',
            'setAsDefaultDelivery' => true,
        ])->assertCreated();

        expect($second->json('data.isDefaultDelivery'))->toBeTrue();
        expect(DB::table('identity.addresses')
            ->where('id', $first->json('data.id'))
            ->value('is_default_delivery'))->toBe(false);
    });
});

describe('GET /identity/addresses', function () {
    it('lists user addresses ordered: default delivery first', function () {
        $session = registerAndLoginCustomer('addr-list@test.local');
        $first = postAddress($session['accessToken'], ['title' => 'Eski'])->assertCreated();
        usleep(2000);
        $second = postAddress($session['accessToken'], [
            'title' => 'Yeni',
            'setAsDefaultDelivery' => true,
        ])->assertCreated();

        $list = test()->getJson(
            '/api/v1/identity/addresses',
            ['Authorization' => 'Bearer ' . $session['accessToken']],
        )->assertOk();

        $titles = array_column($list->json('data'), 'title');
        // Yeni default delivery üstte olmalı
        expect($titles[0])->toBe('Yeni')
            ->and($titles[1])->toBe('Eski');
    });
});

describe('GET /identity/addresses/{id} — IDOR protection', function () {
    it('user A cannot see user B address (404 not 403)', function () {
        $userA = registerAndLoginCustomer('addr-idor-a@test.local');
        $userB = registerAndLoginCustomer('addr-idor-b@test.local');
        $b = postAddress($userB['accessToken'], ['title' => 'B Ev'])->assertCreated();

        test()->getJson(
            '/api/v1/identity/addresses/' . $b->json('data.id'),
            ['Authorization' => 'Bearer ' . $userA['accessToken']],
        )
            ->assertNotFound()
            ->assertJsonPath('error.code', 'ADDRESS_NOT_FOUND');
    });

    it('returns 404 for unknown id', function () {
        $session = registerAndLoginCustomer('addr-404@test.local');
        test()->getJson(
            '/api/v1/identity/addresses/' . Uuid::uuid4()->toString(),
            ['Authorization' => 'Bearer ' . $session['accessToken']],
        )->assertNotFound();
    });
});

describe('PUT /identity/addresses/{id}', function () {
    it('updates fields but does not touch default flags', function () {
        $session = registerAndLoginCustomer('addr-update@test.local');
        $created = postAddress($session['accessToken'], [
            'title' => 'Eski',
            'setAsDefaultDelivery' => true,
        ])->assertCreated();
        $id = $created->json('data.id');

        test()->putJson(
            '/api/v1/identity/addresses/' . $id,
            [
                'title' => 'Yeni Başlık',
                'fullName' => 'Updated Name',
                'phone' => '+905551234567',
                'city' => 'Ankara',
                'district' => 'Çankaya',
                'addressLine' => 'Yeni adres satırı buraya',
            ],
            ['Authorization' => 'Bearer ' . $session['accessToken']],
        )
            ->assertOk()
            ->assertJsonPath('data.title', 'Yeni Başlık')
            ->assertJsonPath('data.city', 'Ankara')
            ->assertJsonPath('data.isDefaultDelivery', true);
    });
});

describe('DELETE /identity/addresses/{id} + default re-pick', function () {
    it('soft deletes + promotes most recent address to default when needed', function () {
        $session = registerAndLoginCustomer('addr-delete@test.local');
        $first = postAddress($session['accessToken'], ['title' => 'Default'])->assertCreated();
        usleep(2000);
        $second = postAddress($session['accessToken'], ['title' => 'İkinci'])->assertCreated();

        // Default first; second is non-default
        test()->deleteJson(
            '/api/v1/identity/addresses/' . $first->json('data.id'),
            [],
            ['Authorization' => 'Bearer ' . $session['accessToken']],
        )->assertNoContent();

        // İkinci adres otomatik default delivery + billing olmuş olmalı
        $row = DB::table('identity.addresses')
            ->where('id', $second->json('data.id'))
            ->first();
        expect((bool) $row->is_default_delivery)->toBeTrue()
            ->and((bool) $row->is_default_billing)->toBeTrue();

        // Silinen adres soft deleted (DB'de hala var, deleted_at dolu)
        $deleted = DB::table('identity.addresses')
            ->where('id', $first->json('data.id'))
            ->first();
        expect($deleted->deleted_at)->not->toBeNull();
    });

    it('rejects delete from another user (404 IDOR)', function () {
        $userA = registerAndLoginCustomer('addr-idor-del-a@test.local');
        $userB = registerAndLoginCustomer('addr-idor-del-b@test.local');
        $b = postAddress($userB['accessToken'])->assertCreated();

        test()->deleteJson(
            '/api/v1/identity/addresses/' . $b->json('data.id'),
            [],
            ['Authorization' => 'Bearer ' . $userA['accessToken']],
        )->assertNotFound();
    });
});

describe('PATCH default-delivery + default-billing', function () {
    it('moves default delivery + idempotent', function () {
        $session = registerAndLoginCustomer('addr-set@test.local');
        $a = postAddress($session['accessToken'], ['title' => 'Adres-A'])->assertCreated();
        $b = postAddress($session['accessToken'], ['title' => 'Adres-B'])->assertCreated();

        // A şu an default; PATCH B'yi default yap
        test()->patchJson(
            '/api/v1/identity/addresses/' . $b->json('data.id') . '/default-delivery',
            [],
            ['Authorization' => 'Bearer ' . $session['accessToken']],
        )->assertOk();

        expect(DB::table('identity.addresses')->where('id', $a->json('data.id'))->value('is_default_delivery'))->toBe(false);
        expect(DB::table('identity.addresses')->where('id', $b->json('data.id'))->value('is_default_delivery'))->toBe(true);

        // İkinci PATCH idempotent
        test()->patchJson(
            '/api/v1/identity/addresses/' . $b->json('data.id') . '/default-delivery',
            [],
            ['Authorization' => 'Bearer ' . $session['accessToken']],
        )->assertOk();
    });
});

describe('Locations endpoints (public)', function () {
    it('GET /locations/cities returns 81 Turkish provinces', function () {
        $response = test()->getJson('/api/v1/locations/cities')->assertOk();
        expect($response->json('data'))->toBeArray()->toHaveCount(81);
    });

    it('GET /locations/cities/İstanbul/districts returns 39 districts', function () {
        $response = test()->getJson('/api/v1/locations/cities/' . rawurlencode('İstanbul') . '/districts')
            ->assertOk();
        expect($response->json('data'))->toBeArray()->toHaveCount(39);
    });

    it('GET unknown city returns 422 INVALID_CITY', function () {
        test()->getJson('/api/v1/locations/cities/Atlantis/districts')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_CITY');
    });
});
