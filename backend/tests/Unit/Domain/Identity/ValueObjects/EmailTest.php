<?php

declare(strict_types=1);

use Domain\Identity\ValueObjects\Email;
use Domain\Shared\Exceptions\ValidationException;

describe('Email value object', function () {
    it('accepts a valid email', function () {
        $email = new Email('user@example.com');

        expect($email->value)->toBe('user@example.com');
    });

    it('lowercases at construction (matches identity.users CHECK)', function () {
        $email = new Email('User@EXAMPLE.com');

        expect($email->value)->toBe('user@example.com');
    });

    it('trims surrounding whitespace', function () {
        $email = new Email('  trim@example.com  ');

        expect($email->value)->toBe('trim@example.com');
    });

    it('rejects empty string', function () {
        new Email('');
    })->throws(ValidationException::class, 'Email cannot be empty');

    it('rejects whitespace-only', function () {
        new Email('   ');
    })->throws(ValidationException::class, 'Email cannot be empty');

    it('rejects malformed email', function () {
        new Email('not-an-email');
    })->throws(ValidationException::class, 'Invalid email format');

    it('rejects email longer than 320 chars', function () {
        // Length-check fires before format-check, so the local-part length
        // is irrelevant — we just need the total to exceed 320.
        new Email(str_repeat('a', 320) . '@x.x'); // total 324 chars
    })->throws(ValidationException::class, 'exceeds 320 characters');

    it('considers same lowercased values equal', function () {
        expect((new Email('A@b.com'))->equals(new Email('a@B.com')))->toBeTrue();
    });

    it('serialises to its string value', function () {
        expect(json_encode(new Email('x@y.com')))->toBe('"x@y.com"');
    });

    it('casts to string', function () {
        expect((string) new Email('s@t.com'))->toBe('s@t.com');
    });
});
