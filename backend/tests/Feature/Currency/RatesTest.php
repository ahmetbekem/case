<?php

declare(strict_types=1);

use Infrastructure\Currency\Models\ExchangeRateModel;
use Ramsey\Uuid\Uuid;

function seedRate(string $base, string $target, float $rate, ?string $date = null): void
{
    ExchangeRateModel::query()->insert([
        'id' => Uuid::uuid4()->toString(),
        'base_currency' => $base,
        'target_currency' => $target,
        'rate' => $rate,
        'rate_date' => $date ?? date('Y-m-d'),
        'fetched_at' => now(),
        'provider' => 'frankfurter',
        'created_at' => now(),
    ]);
}

describe('GET /currencies', function () {
    it('returns the 3 supported currencies with symbols', function () {
        $response = $this->getJson('/api/v1/currencies')->assertOk();

        $codes = array_column($response->json('data'), 'code');
        $symbols = array_column($response->json('data'), 'symbol');

        expect($codes)->toEqualCanonicalizing(['TRY', 'USD', 'EUR'])
            ->and($symbols)->toContain('₺', '$', '€');
    });
});

describe('GET /currencies/{base}/{target}', function () {
    it('returns the latest rate for a known pair', function () {
        seedRate('TRY', 'USD', 0.034);

        $response = $this->getJson('/api/v1/currencies/TRY/USD')->assertOk();

        expect($response->json('data.base'))->toBe('TRY')
            ->and($response->json('data.target'))->toBe('USD')
            ->and($response->json('data.rate'))->toBe(0.034)
            ->and($response->json('data.provider'))->toBe('frankfurter');
    });

    it('picks the most recent row when multiple dates exist', function () {
        seedRate('TRY', 'USD', 0.030, '2026-04-01');  // older
        seedRate('TRY', 'USD', 0.034, '2026-05-04');  // latest

        $response = $this->getJson('/api/v1/currencies/TRY/USD')->assertOk();

        expect($response->json('data.rate'))->toBe(0.034);
    });

    it('returns identity 1.0 when base equals target', function () {
        $response = $this->getJson('/api/v1/currencies/TRY/TRY')->assertOk();

        // JSON encodes 1.0 as "1" and decodes back as int 1 — float-cast first
        expect((float) $response->json('data.rate'))->toBe(1.0)
            ->and($response->json('data.provider'))->toBe('identity');
    });

    it('lowercase path segments are normalised', function () {
        seedRate('TRY', 'USD', 0.034);

        $this->getJson('/api/v1/currencies/try/usd')->assertOk()
            ->assertJsonPath('data.base', 'TRY')
            ->assertJsonPath('data.target', 'USD');
    });

    it('unknown pair returns 404 EXCHANGE_RATE_NOT_FOUND', function () {
        $this->getJson('/api/v1/currencies/TRY/USD')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'EXCHANGE_RATE_NOT_FOUND');
    });

    it('unsupported currency returns 422 UNSUPPORTED_CURRENCY', function () {
        $this->getJson('/api/v1/currencies/TRY/XXX')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'UNSUPPORTED_CURRENCY');
    });
});
