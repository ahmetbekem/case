<?php

declare(strict_types=1);

use Domain\Sales\Entities\Cart;
use Domain\Sales\Services\CartMerger;
use Domain\Sales\Services\MergeOperation;
use Domain\Sales\ValueObjects\GuestSessionId;
use Domain\Shared\ValueObjects\Currency;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\UserId;

/**
 * ADR-017 — CartMerger pure planning servisi. Quantity birleştirme,
 * oldest snapshot price koruma, yalnızca-guest item'ları yeni satır
 * olarak ekleme davranışları.
 */
describe('CartMerger.plan()', function () {
    it('returns no ops for an empty guest cart', function () {
        $userCart = Cart::createForUser(UserId::generate());
        $userCart->addItem('p1', 'P1', 'SKU1', 1, new Money(50.0, Currency::TRY));

        $guestCart = Cart::createForGuest(GuestSessionId::generate());

        $ops = (new CartMerger())->plan($userCart, $guestCart);
        expect($ops)->toBe([]);
    });

    it('emits TYPE_ADD_NEW for items only present in guest', function () {
        $userCart = Cart::createForUser(UserId::generate());
        $guestCart = Cart::createForGuest(GuestSessionId::generate());
        $guestCart->addItem('p1', 'Product 1', 'SKU1', 2, new Money(50.0, Currency::TRY));

        $ops = (new CartMerger())->plan($userCart, $guestCart);

        expect($ops)->toHaveCount(1)
            ->and($ops[0]->type)->toBe(MergeOperation::TYPE_ADD_NEW)
            ->and($ops[0]->productId)->toBe('p1')
            ->and($ops[0]->newQuantity)->toBe(2);
    });

    it('emits TYPE_UPDATE_QUANTITY summing quantities for shared products', function () {
        $userCart = Cart::createForUser(UserId::generate());
        $userCart->addItem('p1', 'P1', 'SKU1', 3, new Money(100.0, Currency::TRY));

        $guestCart = Cart::createForGuest(GuestSessionId::generate());
        $guestCart->addItem('p1', 'P1', 'SKU1', 2, new Money(120.0, Currency::TRY));

        $ops = (new CartMerger())->plan($userCart, $guestCart);

        expect($ops)->toHaveCount(1)
            ->and($ops[0]->type)->toBe(MergeOperation::TYPE_UPDATE_QUANTITY)
            ->and($ops[0]->newQuantity)->toBe(5);
    });

    it('preserves the OLDEST snapshot price when both have the product', function () {
        // User satırı önce — ESKİ snapshot. Guest satırı sonra — YENİ
        // (büyük olasılıkla artmış) fiyat. Beklenti: kullanıcı eski
        // fiyatla devam etsin.
        $userCart = Cart::createForUser(UserId::generate());
        $userCart->addItem('p1', 'P1', 'SKU1', 1, new Money(100.0, Currency::TRY));

        // Guest cart item'ının createdAt'i daha sonra olsun diye küçük bekleme
        usleep(2000);

        $guestCart = Cart::createForGuest(GuestSessionId::generate());
        $guestCart->addItem('p1', 'P1', 'SKU1', 1, new Money(150.0, Currency::TRY));

        $ops = (new CartMerger())->plan($userCart, $guestCart);

        expect($ops[0]->unitPrice->amount)->toBe(100.0);
    });
});

describe('CartMerger.applyToUserCart()', function () {
    it('merges quantities into the user cart aggregate', function () {
        $userCart = Cart::createForUser(UserId::generate());
        $userCart->addItem('p1', 'P1', 'SKU1', 3, new Money(50.0, Currency::TRY));

        $guestCart = Cart::createForGuest(GuestSessionId::generate());
        $guestCart->addItem('p1', 'P1', 'SKU1', 2, new Money(50.0, Currency::TRY));
        $guestCart->addItem('p2', 'P2', 'SKU2', 1, new Money(75.0, Currency::TRY));

        $applied = (new CartMerger())->applyToUserCart($userCart, $guestCart);

        expect($applied)->toBe(2)
            ->and($userCart->itemCount())->toBe(2)
            ->and($userCart->totalQuantity())->toBe(6);
    });
});
