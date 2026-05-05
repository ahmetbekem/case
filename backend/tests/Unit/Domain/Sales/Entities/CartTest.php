<?php

declare(strict_types=1);

use Domain\Sales\Entities\Cart;
use Domain\Sales\Exceptions\CartItemNotFoundException;
use Domain\Sales\ValueObjects\CartId;
use Domain\Sales\ValueObjects\CartItemId;
use Domain\Sales\ValueObjects\OrderId;
use Domain\Shared\ValueObjects\Currency;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\UserId;
use Ramsey\Uuid\Uuid;

function makeCart(): Cart
{
    return new Cart(
        id: CartId::generate(),
        userId: UserId::generate(),
    );
}

describe('Cart aggregate', function () {
    it('starts empty', function () {
        $cart = makeCart();
        expect($cart->isEmpty())->toBeTrue()
            ->and($cart->itemCount())->toBe(0)
            ->and($cart->totalQuantity())->toBe(0)
            ->and($cart->isActive())->toBeTrue();
    });

    it('addItem appends a new line for an unseen product', function () {
        $cart = makeCart();
        $productId = Uuid::uuid4()->toString();

        $cart->addItem($productId, 2, new Money(50.0, Currency::TRY));

        expect($cart->itemCount())->toBe(1)
            ->and($cart->totalQuantity())->toBe(2)
            ->and($cart->subtotal()->amount)->toBe(100.0);
    });

    it('addItem on the same product increments quantity instead of duplicating', function () {
        $cart = makeCart();
        $productId = Uuid::uuid4()->toString();

        $cart->addItem($productId, 2, new Money(50.0, Currency::TRY));
        $cart->addItem($productId, 3, new Money(50.0, Currency::TRY));

        expect($cart->itemCount())->toBe(1)
            ->and($cart->totalQuantity())->toBe(5)
            ->and($cart->subtotal()->amount)->toBe(250.0);
    });

    it('updateItemQuantity changes a known item', function () {
        $cart = makeCart();
        $productId = Uuid::uuid4()->toString();
        $item = $cart->addItem($productId, 2, new Money(50.0, Currency::TRY));

        $cart->updateItemQuantity($item->getId(), 5);

        expect($cart->totalQuantity())->toBe(5);
    });

    it('updateItemQuantity throws CartItemNotFoundException for unknown id', function () {
        $cart = makeCart();
        $cart->updateItemQuantity(CartItemId::generate(), 5);
    })->throws(CartItemNotFoundException::class);

    it('removeItem drops the matching line', function () {
        $cart = makeCart();
        $a = $cart->addItem(Uuid::uuid4()->toString(), 1, new Money(10.0, Currency::TRY));
        $b = $cart->addItem(Uuid::uuid4()->toString(), 2, new Money(20.0, Currency::TRY));

        $cart->removeItem($a->getId());

        expect($cart->itemCount())->toBe(1)
            ->and($cart->getItems()[0]->getId()->value())->toBe($b->getId()->value());
    });

    it('removeItem throws CartItemNotFoundException for unknown id', function () {
        $cart = makeCart();
        $cart->removeItem(CartItemId::generate());
    })->throws(CartItemNotFoundException::class);

    it('clear empties the cart', function () {
        $cart = makeCart();
        $cart->addItem(Uuid::uuid4()->toString(), 1, new Money(10.0, Currency::TRY));
        $cart->addItem(Uuid::uuid4()->toString(), 2, new Money(20.0, Currency::TRY));

        $cart->clear();

        expect($cart->isEmpty())->toBeTrue();
    });

    it('markAsConverted flips status and stamps the order id', function () {
        $cart = makeCart();
        $cart->addItem(Uuid::uuid4()->toString(), 1, new Money(10.0, Currency::TRY));
        $orderId = OrderId::generate();

        $cart->markAsConverted($orderId);

        expect($cart->getStatus())->toBe(Cart::STATUS_CONVERTED)
            ->and($cart->getConvertedToOrderId()?->value())->toBe($orderId->value())
            ->and($cart->isActive())->toBeFalse();
    });

    it('markAsConverted on a non-active cart throws LogicException', function () {
        $cart = makeCart();
        $cart->addItem(Uuid::uuid4()->toString(), 1, new Money(10.0, Currency::TRY));
        $cart->markAsConverted(OrderId::generate());

        $cart->markAsConverted(OrderId::generate());
    })->throws(LogicException::class);

    it('mutating a converted cart throws', function () {
        $cart = makeCart();
        $cart->addItem(Uuid::uuid4()->toString(), 1, new Money(10.0, Currency::TRY));
        $cart->markAsConverted(OrderId::generate());

        $cart->addItem(Uuid::uuid4()->toString(), 1, new Money(10.0, Currency::TRY));
    })->throws(LogicException::class);

    it('subtotal sums lineTotal across items in the same currency', function () {
        $cart = makeCart();
        $cart->addItem(Uuid::uuid4()->toString(), 2, new Money(10.0, Currency::TRY)); // 20
        $cart->addItem(Uuid::uuid4()->toString(), 3, new Money(15.50, Currency::TRY)); // 46.50

        expect($cart->subtotal()->amount)->toBe(66.50)
            ->and($cart->subtotal()->currency)->toBe(Currency::TRY);
    });
});
