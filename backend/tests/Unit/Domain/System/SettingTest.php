<?php

declare(strict_types=1);

use Domain\System\Entities\Setting;
use Domain\System\ValueObjects\SettingKey;

describe('Setting entity', function () {
    it('factory creates entry with sane defaults', function () {
        $s = Setting::create('foo', 'bar');

        expect($s->getKey())->toBe('foo')
            ->and($s->getValue())->toBe('bar')
            ->and($s->getType())->toBe('string')
            ->and($s->getCategory())->toBe('general')
            ->and($s->isPublic())->toBeFalse()
            ->and($s->getUpdatedAt())->toBeNull();
    });

    it('changeValue marks as updated', function () {
        $s = Setting::create('k', 'v1');
        $s->changeValue('v2');

        expect($s->getValue())->toBe('v2')
            ->and($s->getUpdatedAt())->not->toBeNull();
    });

    it('changeValue with same value is a no-op (no audit churn)', function () {
        $s = Setting::create('k', 'v1');
        $s->changeValue('v1');

        expect($s->getUpdatedAt())->toBeNull();
    });

    it('typedValue casts number/boolean/json correctly', function () {
        expect(Setting::create('a', '42', 'number')->getTypedValue())->toBe(42)
            ->and(Setting::create('b', '3.14', 'number')->getTypedValue())->toBe(3.14)
            ->and(Setting::create('c', 'true', 'boolean')->getTypedValue())->toBeTrue()
            ->and(Setting::create('d', 'false', 'boolean')->getTypedValue())->toBeFalse()
            ->and(Setting::create('e', '["TRY","USD"]', 'json')->getTypedValue())
                ->toEqual(['TRY', 'USD']);
    });
});

describe('SettingKey constants', function () {
    it('exposes all known keys', function () {
        expect(SettingKey::all())->toContain(
            SettingKey::BASE_CURRENCY,
            SettingKey::DEFAULT_DISPLAY_CURRENCY,
            SettingKey::SUPPORTED_CURRENCIES,
            SettingKey::SITE_NAME,
        );
    });

    it('has private constructor (utility class only)', function () {
        $reflection = new \ReflectionClass(SettingKey::class);
        expect($reflection->getConstructor()?->isPrivate())->toBeTrue();
    });
});
