<?php

declare(strict_types=1);

use Domain\Shared\Exceptions\ValidationException;
use Domain\Shared\ValueObjects\Currency;
use Domain\Shared\ValueObjects\Money;

describe('Money value object', function () {
    it('constructs with amount and currency', function () {
        $money = new Money(100.50, Currency::TRY);

        expect($money->amount)->toBe(100.50)
            ->and($money->currency)->toBe(Currency::TRY);
    });

    it('builds from raw amount and currency code via fromAmount', function () {
        $money = Money::fromAmount(42.0, 'USD');

        expect($money->amount)->toBe(42.0)
            ->and($money->currency)->toBe(Currency::USD);
    });

    it('rejects negative amounts at construction', function () {
        new Money(-1.0, Currency::USD);
    })->throws(ValidationException::class, 'Money amount cannot be negative');

    it('serialises via value() to amount + currency code', function () {
        $money = new Money(99.99, Currency::EUR);

        expect($money->value())->toBe([
            'amount' => 99.99,
            'currency' => 'EUR',
        ]);
    });

    it('json-serialises through value()', function () {
        $money = new Money(10.0, Currency::TRY);

        expect(json_encode($money))->toBe('{"amount":10,"currency":"TRY"}');
    });

    it('considers same amount + currency as equal', function () {
        $a = new Money(50.0, Currency::TRY);
        $b = new Money(50.0, Currency::TRY);

        expect($a->equals($b))->toBeTrue();
    });

    it('treats different amounts as not equal', function () {
        $a = new Money(50.0, Currency::TRY);
        $b = new Money(50.01, Currency::TRY);

        expect($a->equals($b))->toBeFalse();
    });

    it('treats different currencies as not equal', function () {
        $a = new Money(50.0, Currency::TRY);
        $b = new Money(50.0, Currency::USD);

        expect($a->equals($b))->toBeFalse();
    });

    it('adds two amounts in the same currency', function () {
        $sum = (new Money(10.0, Currency::TRY))->add(new Money(15.50, Currency::TRY));

        expect($sum->amount)->toBe(25.50)
            ->and($sum->currency)->toBe(Currency::TRY);
    });

    it('rejects adding different currencies', function () {
        (new Money(10.0, Currency::TRY))->add(new Money(10.0, Currency::USD));
    })->throws(ValidationException::class, 'Cannot add amounts in different currencies');

    it('subtracts amounts in the same currency', function () {
        $diff = (new Money(20.0, Currency::EUR))->subtract(new Money(7.50, Currency::EUR));

        expect($diff->amount)->toBe(12.50);
    });

    it('rejects subtraction that would underflow to negative', function () {
        (new Money(5.0, Currency::TRY))->subtract(new Money(10.0, Currency::TRY));
    })->throws(ValidationException::class, 'would yield a negative amount');

    it('multiplies by a non-negative integer factor', function () {
        $tripled = (new Money(10.0, Currency::USD))->multiply(3);

        expect($tripled->amount)->toBe(30.0)
            ->and($tripled->currency)->toBe(Currency::USD);
    });

    it('rejects negative multiplication factor', function () {
        (new Money(10.0, Currency::USD))->multiply(-2);
    })->throws(ValidationException::class, 'Money multiplier cannot be negative');

    it('zero amount is allowed', function () {
        $zero = new Money(0.0, Currency::TRY);

        expect($zero->amount)->toBe(0.0);
    });
});

describe('Currency enum', function () {
    it('exposes correct symbols', function () {
        expect(Currency::TRY->symbol())->toBe('₺')
            ->and(Currency::USD->symbol())->toBe('$')
            ->and(Currency::EUR->symbol())->toBe('€');
    });
});
