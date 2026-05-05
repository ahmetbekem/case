<?php

declare(strict_types=1);

use Domain\Sales\Entities\Order;
use Domain\Sales\Entities\OrderItem;
use Domain\Sales\Enums\OrderStatus;
use Domain\Sales\Exceptions\OrderAccessDeniedException;
use Domain\Sales\Exceptions\OrderCannotBeCancelledException;
use Domain\Sales\ValueObjects\OrderId;
use Domain\Sales\ValueObjects\OrderItemId;
use Domain\Shared\ValueObjects\Currency;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\UserId;
use Ramsey\Uuid\Uuid;

function makeOrderItem(OrderId $orderId, float $unit = 100.0, int $qty = 2): OrderItem
{
    return new OrderItem(
        id: OrderItemId::generate(),
        orderId: $orderId,
        productId: Uuid::uuid4()->toString(),
        productNameSnapshot: 'Sample',
        productSkuSnapshot: 'SAMPLE-001',
        quantity: $qty,
        unitPrice: new Money($unit, Currency::TRY),
        lineTotal: new Money($unit * $qty, Currency::TRY),
    );
}

function makeOrder(UserId $userId, OrderStatus $status = OrderStatus::PENDING): Order
{
    $orderId = OrderId::generate();
    return new Order(
        id: $orderId,
        orderNumber: 'ORD-2026-000001',
        userId: $userId,
        status: $status,
        subtotal: new Money(200.0, Currency::TRY),
        total: new Money(200.0, Currency::TRY),
        displayCurrency: Currency::USD,
        displayTotal: new Money(6.80, Currency::USD),
        exchangeRateSnapshot: 0.034,
        items: [makeOrderItem($orderId)],
    );
}

describe('Order entity', function () {
    it('refuses to construct with no items', function () {
        new Order(
            id: OrderId::generate(),
            orderNumber: 'ORD-2026-000999',
            userId: UserId::generate(),
            status: OrderStatus::PENDING,
            subtotal: new Money(0.0, Currency::TRY),
            total: new Money(0.0, Currency::TRY),
            displayCurrency: Currency::TRY,
            displayTotal: new Money(0.0, Currency::TRY),
            exchangeRateSnapshot: 1.0,
            items: [],
        );
    })->throws(InvalidArgumentException::class);

    it('refuses non-positive exchangeRateSnapshot', function () {
        $orderId = OrderId::generate();
        new Order(
            id: $orderId,
            orderNumber: 'X',
            userId: UserId::generate(),
            status: OrderStatus::PENDING,
            subtotal: new Money(10.0, Currency::TRY),
            total: new Money(10.0, Currency::TRY),
            displayCurrency: Currency::TRY,
            displayTotal: new Money(10.0, Currency::TRY),
            exchangeRateSnapshot: 0.0,
            items: [makeOrderItem($orderId)],
        );
    })->throws(InvalidArgumentException::class);
});

describe('Order state machine', function () {
    it('cancel from pending sets status, cancelledAt, reason', function () {
        $order = makeOrder(UserId::generate());

        $order->cancel('Changed my mind');

        expect($order->getStatus())->toBe(OrderStatus::CANCELLED)
            ->and($order->getCancelledAt())->not->toBeNull()
            ->and($order->getCancelledReason())->toBe('Changed my mind');
    });

    it('cancel rejects empty reason', function () {
        $order = makeOrder(UserId::generate());
        $order->cancel('   ');
    })->throws(InvalidArgumentException::class);

    it('cancel from completed throws OrderCannotBeCancelledException', function () {
        $order = makeOrder(UserId::generate());
        $order->complete();

        $order->cancel('too late');
    })->throws(OrderCannotBeCancelledException::class);

    it('cancel from already-cancelled throws OrderCannotBeCancelledException', function () {
        $order = makeOrder(UserId::generate());
        $order->cancel('first');

        $order->cancel('second');
    })->throws(OrderCannotBeCancelledException::class);

    it('complete from pending sets status + completedAt', function () {
        $order = makeOrder(UserId::generate());
        $order->complete();

        expect($order->getStatus())->toBe(OrderStatus::COMPLETED)
            ->and($order->getCompletedAt())->not->toBeNull();
    });
});

describe('Order IDOR guard — assertCanBeViewedBy', function () {
    it('owner can view their own order', function () {
        $owner = UserId::generate();
        $order = makeOrder($owner);

        $order->assertCanBeViewedBy($owner, 'customer');

        expect(true)->toBeTrue(); // no exception = pass
    });

    it('admin can view any order', function () {
        $owner = UserId::generate();
        $admin = UserId::generate();
        $order = makeOrder($owner);

        $order->assertCanBeViewedBy($admin, 'admin');

        expect(true)->toBeTrue();
    });

    it('non-owner non-admin throws OrderAccessDeniedException (rendered as 404)', function () {
        $owner = UserId::generate();
        $other = UserId::generate();
        $order = makeOrder($owner);

        $order->assertCanBeViewedBy($other, 'customer');
    })->throws(OrderAccessDeniedException::class);

    it('OrderAccessDeniedException maps to 404 (NOT 403) — anti-enumeration', function () {
        $exception = OrderAccessDeniedException::notOwner();
        expect($exception->getHttpStatusCode())->toBe(404)
            ->and($exception->errorCode)->toBe('ORDER_NOT_FOUND');
    });
});
