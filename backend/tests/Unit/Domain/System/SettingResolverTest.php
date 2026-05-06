<?php

declare(strict_types=1);

use Domain\Shared\ValueObjects\Currency;
use Domain\System\Contracts\SettingRepositoryInterface;
use Domain\System\Entities\Setting;
use Domain\System\Services\SettingResolver;
use Domain\System\ValueObjects\SettingKey;

beforeEach(function () {
    $this->repo = Mockery::mock(SettingRepositoryInterface::class);
    $this->resolver = new SettingResolver($this->repo);
});

describe('getBaseCurrency', function () {
    it('returns Currency from setting value', function () {
        $this->repo->shouldReceive('findByKey')
            ->with(SettingKey::BASE_CURRENCY)
            ->andReturn(Setting::create('base_currency', 'USD'));

        expect($this->resolver->getBaseCurrency())->toBe(Currency::USD);
    });

    it('falls back to TRY when setting missing', function () {
        $this->repo->shouldReceive('findByKey')->andReturn(null);
        expect($this->resolver->getBaseCurrency())->toBe(Currency::TRY);
    });

    it('falls back to TRY when stored value is invalid', function () {
        $this->repo->shouldReceive('findByKey')
            ->andReturn(Setting::create('base_currency', 'XYZ'));
        expect($this->resolver->getBaseCurrency())->toBe(Currency::TRY);
    });
});

describe('getDefaultDisplayCurrency', function () {
    it('returns Currency from setting', function () {
        $this->repo->shouldReceive('findByKey')
            ->with(SettingKey::DEFAULT_DISPLAY_CURRENCY)
            ->andReturn(Setting::create('default_display_currency', 'EUR'));

        expect($this->resolver->getDefaultDisplayCurrency())->toBe(Currency::EUR);
    });

    it('falls back to TRY when missing', function () {
        $this->repo->shouldReceive('findByKey')->andReturn(null);
        expect($this->resolver->getDefaultDisplayCurrency())->toBe(Currency::TRY);
    });
});

describe('getSupportedCurrencies', function () {
    it('parses json array into Currency enum list', function () {
        $this->repo->shouldReceive('findByKey')
            ->with(SettingKey::SUPPORTED_CURRENCIES)
            ->andReturn(Setting::create(
                'supported_currencies',
                json_encode(['TRY', 'USD']),
                'json',
            ));

        expect($this->resolver->getSupportedCurrencies())
            ->toEqual([Currency::TRY, Currency::USD]);
    });

    it('returns all enum cases when setting missing', function () {
        $this->repo->shouldReceive('findByKey')->andReturn(null);

        expect($this->resolver->getSupportedCurrencies())
            ->toEqual(Currency::cases());
    });

    it('skips invalid currency codes', function () {
        $this->repo->shouldReceive('findByKey')
            ->andReturn(Setting::create(
                'supported_currencies',
                json_encode(['TRY', 'XYZ', 'USD']),
                'json',
            ));

        expect($this->resolver->getSupportedCurrencies())
            ->toEqual([Currency::TRY, Currency::USD]);
    });

    it('falls back to all cases on malformed json', function () {
        $this->repo->shouldReceive('findByKey')
            ->andReturn(Setting::create('supported_currencies', 'not-json', 'json'));

        expect($this->resolver->getSupportedCurrencies())
            ->toEqual(Currency::cases());
    });
});
