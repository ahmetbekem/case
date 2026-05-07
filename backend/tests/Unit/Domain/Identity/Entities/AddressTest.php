<?php

declare(strict_types=1);

use Domain\Identity\Entities\Address;
use Domain\Identity\ValueObjects\AddressId;
use Domain\Identity\ValueObjects\PhoneNumber;
use Domain\Shared\ValueObjects\UserId;

function makeAddress(array $overrides = []): Address
{
    return new Address(
        id: $overrides['id'] ?? AddressId::generate(),
        userId: $overrides['userId'] ?? UserId::generate(),
        title: $overrides['title'] ?? 'Ev',
        fullName: $overrides['fullName'] ?? 'Test User',
        phone: $overrides['phone'] ?? PhoneNumber::fromString('+905551234567'),
        countryCode: $overrides['countryCode'] ?? 'TR',
        city: $overrides['city'] ?? 'İstanbul',
        district: $overrides['district'] ?? 'Kadıköy',
        neighborhood: $overrides['neighborhood'] ?? 'Caferağa',
        postalCode: $overrides['postalCode'] ?? '34710',
        addressLine: $overrides['addressLine'] ?? 'Moda Cad. No: 123',
        addressLine2: $overrides['addressLine2'] ?? null,
        isDefaultDelivery: $overrides['isDefaultDelivery'] ?? false,
        isDefaultBilling: $overrides['isDefaultBilling'] ?? false,
    );
}

describe('Address entity invariants', function () {
    it('rejects empty title', function () {
        makeAddress(['title' => '   ']);
    })->throws(\InvalidArgumentException::class, 'title is required');

    it('rejects title over 50 chars', function () {
        makeAddress(['title' => str_repeat('a', 51)]);
    })->throws(\InvalidArgumentException::class, 'title too long');

    it('rejects address line shorter than 10 chars', function () {
        makeAddress(['addressLine' => 'Short']);
    })->throws(\InvalidArgumentException::class, 'too short');

    it('rejects non-TR country code', function () {
        makeAddress(['countryCode' => 'US']);
    })->throws(\InvalidArgumentException::class, 'country code is supported');

    it('rejects empty city or district', function () {
        makeAddress(['city' => '   ']);
    })->throws(\InvalidArgumentException::class, 'City and district');
});

describe('Address.toSnapshot()', function () {
    it('includes all required fields + snapshotAt timestamp', function () {
        $address = makeAddress(['title' => 'Ev', 'city' => 'İstanbul', 'district' => 'Kadıköy']);
        $snapshot = $address->toSnapshot();

        expect($snapshot)->toHaveKeys([
            'addressId',
            'title',
            'fullName',
            'phone',
            'countryCode',
            'city',
            'district',
            'neighborhood',
            'postalCode',
            'addressLine',
            'addressLine2',
            'snapshotAt',
        ])
            ->and($snapshot['title'])->toBe('Ev')
            ->and($snapshot['city'])->toBe('İstanbul')
            ->and($snapshot['district'])->toBe('Kadıköy')
            ->and($snapshot['snapshotAt'])->toBeString();
    });
});

describe('Address default flag mutations', function () {
    it('setAsDefaultDelivery is idempotent', function () {
        $address = makeAddress(['isDefaultDelivery' => true]);
        $before = $address->getUpdatedAt();
        $address->setAsDefaultDelivery();
        expect($address->getUpdatedAt())->toBe($before); // no markAsUpdated
    });

    it('unsetDefaultDelivery flips the flag', function () {
        $address = makeAddress(['isDefaultDelivery' => true]);
        expect($address->isDefaultDelivery())->toBeTrue();
        $address->unsetDefaultDelivery();
        expect($address->isDefaultDelivery())->toBeFalse();
    });
});

describe('PhoneNumber VO normalization', function () {
    it('normalizes 0-prefixed local TR phone to +90E.164', function () {
        expect(PhoneNumber::fromString('0 555 123 45 67')->value())->toBe('+905551234567');
    });

    it('accepts +90 prefix as-is', function () {
        expect(PhoneNumber::fromString('+905551234567')->value())->toBe('+905551234567');
    });

    it('accepts raw 10-digit TR number and prepends +90', function () {
        expect(PhoneNumber::fromString('5551234567')->value())->toBe('+905551234567');
    });

    it('strips formatting characters (parens, dashes, spaces)', function () {
        expect(PhoneNumber::fromString('+90 (555) 123-45-67')->value())->toBe('+905551234567');
    });

    it('rejects non-digit input', function () {
        PhoneNumber::fromString('not-a-phone');
    })->throws(\Domain\Shared\Exceptions\ValidationException::class);

    it('toDisplay returns Turkish 0-prefixed format', function () {
        $phone = PhoneNumber::fromString('+905551234567');
        expect($phone->toDisplay())->toBe('0 555 123 45 67');
    });
});
