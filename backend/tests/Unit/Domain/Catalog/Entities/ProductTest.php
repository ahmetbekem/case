<?php

declare(strict_types=1);

use Domain\Catalog\Entities\Product;
use Domain\Catalog\Exceptions\InsufficientStockException;
use Domain\Catalog\ValueObjects\ProductId;
use Domain\Shared\ValueObjects\Currency;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\UserId;

function makeProduct(int $stock = 10, float $price = 100.0): Product
{
    return new Product(
        id: ProductId::generate(),
        sku: 'TEST-001',
        name: 'Test Product',
        description: null,
        price: new Money($price, Currency::TRY),
        stock: $stock,
        isActive: true,
        createdBy: UserId::generate(),
    );
}

describe('Product entity', function () {
    it('refuses negative initial stock', function () {
        new Product(
            id: ProductId::generate(),
            sku: 'X',
            name: 'X',
            description: null,
            price: new Money(1.0, Currency::TRY),
            stock: -5,
            isActive: true,
            createdBy: UserId::generate(),
        );
    })->throws(InvalidArgumentException::class);

    it('decreases stock correctly', function () {
        $product = makeProduct(stock: 10);
        $product->decreaseStock(3);
        expect($product->getStock())->toBe(7);
    });

    it('throws InsufficientStockException when decrease would dip below zero', function () {
        $product = makeProduct(stock: 5);
        $product->decreaseStock(10);
    })->throws(InsufficientStockException::class);

    it('rejects decrease by zero', function () {
        $product = makeProduct();
        $product->decreaseStock(0);
    })->throws(InvalidArgumentException::class, 'must be positive');

    it('rejects decrease by negative', function () {
        $product = makeProduct();
        $product->decreaseStock(-1);
    })->throws(InvalidArgumentException::class, 'must be positive');

    it('increases stock correctly', function () {
        $product = makeProduct(stock: 5);
        $product->increaseStock(7);
        expect($product->getStock())->toBe(12);
    });

    it('rename rejects empty / whitespace', function () {
        $product = makeProduct();
        $product->rename('   ');
    })->throws(InvalidArgumentException::class);

    it('changePrice replaces the Money VO', function () {
        $product = makeProduct(price: 100.0);
        $product->changePrice(new Money(150.50, Currency::USD));
        expect($product->getPrice()->amount)->toBe(150.50)
            ->and($product->getPrice()->currency)->toBe(Currency::USD);
    });

    it('activate / deactivate flips the flag', function () {
        $product = makeProduct();
        $product->deactivate();
        expect($product->isActive())->toBeFalse();
        $product->activate();
        expect($product->isActive())->toBeTrue();
    });

    it('marks updatedAt on every state change', function () {
        $product = makeProduct();
        expect($product->getUpdatedAt())->toBeNull();

        $product->rename('New Name');

        expect($product->getUpdatedAt())->not->toBeNull();
    });

    it('setStock rejects negative', function () {
        $product = makeProduct();
        $product->setStock(-1);
    })->throws(InvalidArgumentException::class);

    it('setStock zero is allowed (sold out)', function () {
        $product = makeProduct(stock: 5);
        $product->setStock(0);
        expect($product->getStock())->toBe(0);
    });
});
