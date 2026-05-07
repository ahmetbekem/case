<?php

declare(strict_types=1);

use Domain\Sales\Entities\Cart;
use Domain\Sales\ValueObjects\CartId;
use Domain\Sales\ValueObjects\GuestSessionId;
use Domain\Shared\ValueObjects\UserId;

/**
 * ADR-017 — Cart entity guest cart davranışları. Konstruktör XOR
 * invariant'ı, named constructor'lar, isGuest/isUser, expires_at
 * yönetimi.
 */
describe('Cart guest cart invariants', function () {
    it('rejects construction with both userId and guestSessionId', function () {
        new Cart(
            id: CartId::generate(),
            userId: UserId::generate(),
            guestSessionId: GuestSessionId::generate(),
        );
    })->throws(\InvalidArgumentException::class, 'cannot have both');

    it('rejects construction without any owner', function () {
        new Cart(
            id: CartId::generate(),
            userId: null,
            guestSessionId: null,
        );
    })->throws(\InvalidArgumentException::class, 'must have either');

    it('createForUser returns user cart with no expiry', function () {
        $userId = UserId::generate();
        $cart = Cart::createForUser($userId);

        expect($cart->isUser())->toBeTrue()
            ->and($cart->isGuest())->toBeFalse()
            ->and($cart->getUserId()?->value())->toBe($userId->value())
            ->and($cart->getGuestSessionId())->toBeNull()
            ->and($cart->getExpiresAt())->toBeNull()
            ->and($cart->isExpired())->toBeFalse();
    });

    it('createForGuest stamps expires_at = now + 30 days', function () {
        $sessionId = GuestSessionId::generate();
        $cart = Cart::createForGuest($sessionId);

        expect($cart->isGuest())->toBeTrue()
            ->and($cart->isUser())->toBeFalse()
            ->and($cart->getGuestSessionId()?->value())->toBe($sessionId->value())
            ->and($cart->getUserId())->toBeNull()
            ->and($cart->getExpiresAt())->not->toBeNull()
            ->and($cart->isExpired())->toBeFalse();

        $expectedTtlDays = Cart::GUEST_CART_TTL_DAYS;
        $diffSeconds = $cart->getExpiresAt()->getTimestamp() - time();
        // Tolerance — testin sürmesi sırasında geçen saniyeler için
        expect($diffSeconds)->toBeGreaterThan($expectedTtlDays * 24 * 60 * 60 - 60)
            ->toBeLessThanOrEqual($expectedTtlDays * 24 * 60 * 60);
    });

    it('isExpired returns true when expires_at is in the past', function () {
        $cart = new Cart(
            id: CartId::generate(),
            guestSessionId: GuestSessionId::generate(),
            expiresAt: new \DateTimeImmutable('-1 day'),
        );

        expect($cart->isExpired())->toBeTrue();
    });

    it('toArray exposes guest fields', function () {
        $cart = Cart::createForGuest(GuestSessionId::generate());
        $arr = $cart->toArray();

        expect($arr)->toHaveKeys(['guestSessionId', 'isGuest', 'expiresAt', 'userId'])
            ->and($arr['isGuest'])->toBeTrue()
            ->and($arr['userId'])->toBeNull()
            ->and($arr['guestSessionId'])->not->toBeNull()
            ->and($arr['expiresAt'])->not->toBeNull();
    });

    it('fromArray round-trips a guest cart preserving owner XOR', function () {
        $sessionId = GuestSessionId::generate();
        $original = Cart::createForGuest($sessionId);
        $restored = Cart::fromArray($original->toArray());

        expect($restored->isGuest())->toBeTrue()
            ->and($restored->getGuestSessionId()?->value())->toBe($sessionId->value())
            ->and($restored->getUserId())->toBeNull()
            ->and($restored->getExpiresAt())->not->toBeNull();
    });
});
