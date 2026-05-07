<?php

declare(strict_types=1);

namespace Application\Sales\Handlers\Commands;

use Application\Sales\Commands\PlaceOrderCommand;
use Application\Sales\DTOs\OrderDTO;
use Application\Shared\Abstracts\AbstractCommandHandler;
use Application\Shared\Contracts\CommandInterface;
use Application\Shared\Contracts\TransactionInterface;
use Domain\Catalog\Contracts\ProductRepositoryInterface;
use Domain\Catalog\Exceptions\InsufficientStockException;
use Domain\Catalog\Exceptions\ProductNotFoundException;
use Domain\Catalog\ValueObjects\ProductId;
use Domain\Currency\Contracts\ExchangeRateRepositoryInterface;
use Domain\Currency\Entities\ExchangeRate;
use Domain\Currency\Services\CurrencyConverter;
use Domain\Identity\Contracts\AddressRepositoryInterface;
use Domain\Identity\Exceptions\AddressNotFoundException;
use Domain\Identity\ValueObjects\AddressId;
use Domain\Logging\Contracts\AuditLoggerInterface;
use Domain\Logging\ValueObjects\AuditEvent;
use Domain\Sales\Contracts\CartRepositoryInterface;
use Domain\Sales\Contracts\OrderRepositoryInterface;
use Domain\Sales\Entities\Order;
use Domain\Sales\Entities\OrderItem;
use Domain\Sales\Enums\OrderStatus;
use Domain\Sales\Exceptions\EmptyCartException;
use Domain\Sales\Exceptions\MixedCurrencyCartException;
use Domain\Sales\ValueObjects\OrderId;
use Domain\Sales\ValueObjects\OrderItemId;
use Domain\Shared\ValueObjects\Currency;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\UserId;
use Psr\Log\LoggerInterface;

/**
 * Place-order pipeline (atomic via TransactionInterface):
 *   1. Load active cart, refuse if empty
 *   2. Pre-load latest TRY-based exchange rates (single query)
 *   3. For each cart item:
 *        a. Load product (404 if missing)
 *        b. decreaseStock (409 INSUFFICIENT_STOCK if would underflow)
 *        c. Build OrderItem snapshot from product + cart price snapshot
 *      Track running TRY subtotal as we go.
 *   4. Convert subtotal to display currency via CurrencyConverter
 *   5. Generate order number from sales.order_number_seq
 *   6. Construct Order, save (cascades order_items)
 *   7. Mark cart as converted (status='converted', converted_to_order_id set)
 *   8. Emit `sales.order_place` audit
 *
 * Cart prices are snapshotted at add-to-cart time, so a price change
 * between add and checkout does NOT silently re-price the order — the
 * customer pays what they saw.
 */
final class PlaceOrderHandler extends AbstractCommandHandler
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ExchangeRateRepositoryInterface $exchangeRateRepository,
        private readonly CurrencyConverter $currencyConverter,
        private readonly AddressRepositoryInterface $addressRepository,
        private readonly AuditLoggerInterface $auditLogger,
        TransactionInterface $transaction,
        LoggerInterface $logger,
    ) {
        parent::__construct($transaction, $logger);
    }

    public static function getHandledCommandClass(): string
    {
        return PlaceOrderCommand::class;
    }

    /**
     * @throws EmptyCartException
     * @throws ProductNotFoundException
     * @throws InsufficientStockException
     */
    protected function doHandle(CommandInterface $command): OrderDTO
    {
        assert($command instanceof PlaceOrderCommand);

        $userId = UserId::fromString($command->getUserId());
        $displayCurrency = Currency::from($command->displayCurrency);

        // ADR-018 — Adres validation IDOR-safe (findByIdAndUser).
        // Cart/stock kontrolünden ÖNCE: kullanıcı geçersiz adres
        // gönderirse stok düşmesin / cart'ı convert etmeye başlamayalım.
        $deliveryAddress = $this->addressRepository->findByIdAndUser(
            AddressId::fromString($command->deliveryAddressId),
            $userId,
        );
        if ($deliveryAddress === null) {
            throw AddressNotFoundException::withId($command->deliveryAddressId);
        }

        // Billing null → delivery ile aynı (UX kısayolu)
        $billingAddressId = $command->billingAddressId ?? $command->deliveryAddressId;
        if ($billingAddressId === $command->deliveryAddressId) {
            $billingAddress = $deliveryAddress;
        } else {
            $billingAddress = $this->addressRepository->findByIdAndUser(
                AddressId::fromString($billingAddressId),
                $userId,
            );
            if ($billingAddress === null) {
                throw AddressNotFoundException::withId($billingAddressId);
            }
        }

        $cart = $this->cartRepository->findActiveCartForUser($userId);
        if ($cart === null || $cart->isEmpty()) {
            throw EmptyCartException::cannotPlaceOrder();
        }
        // Cart aggregate mixed currency'e izin veriyor (UI'da rahat eklenebilsin)
        // ama checkout matematiği tek currency varsayıyor — burada erkenden 409
        if (!$cart->isUniformCurrency()) {
            throw MixedCurrencyCartException::cannotPlaceOrder(
                array_keys($cart->subtotalsByCurrency()),
            );
        }

        // Order base currency = cart'ın uniform currency'si (ilk item).
        // System base_currency hardcode değil — eski mixed verili
        // ürünlerle de doğru çalışsın diye cart-level alıyoruz.
        // ADIM 6 alignment migration sonrası bu zaten system base'e eşit olacak.
        $baseCurrency = $cart->subtotal()->currency;
        $rateMap = $this->loadRateMap($baseCurrency);

        $orderId = OrderId::generate();
        $orderItems = [];
        $subtotalAmount = 0.0;

        foreach ($cart->getItems() as $cartItem) {
            $product = $this->productRepository->findById(
                ProductId::fromString($cartItem->getProductId()),
            );
            if ($product === null) {
                throw ProductNotFoundException::withId($cartItem->getProductId());
            }

            // Decrease stock — atomic with the rest of the transaction.
            // Throws InsufficientStockException (409) if would underflow.
            $product->decreaseStock($cartItem->getQuantity());
            $this->productRepository->save($product);

            $unitPrice = $cartItem->getUnitPriceSnapshot();
            $lineTotal = $unitPrice->multiply($cartItem->getQuantity());

            $orderItems[] = new OrderItem(
                id: OrderItemId::generate(),
                orderId: $orderId,
                productId: $product->getId()->value(),
                productNameSnapshot: $product->getName(),
                productSkuSnapshot: $product->getSku(),
                quantity: $cartItem->getQuantity(),
                unitPrice: $unitPrice,
                lineTotal: $lineTotal,
            );

            $subtotalAmount += $lineTotal->amount;
        }

        $subtotal = new Money(round($subtotalAmount, 2), $baseCurrency);
        $total = $subtotal; // No tax/discount in current scope
        $displayTotal = $this->currencyConverter->convert($total, $displayCurrency, $rateMap);

        $exchangeRateUsed = $displayCurrency === $baseCurrency
            ? 1.0
            : $rateMap[$baseCurrency->value . '-' . $displayCurrency->value]->getRate();

        $orderNumber = $this->orderRepository->generateNumber();

        // ADR-018 — Adres snapshot'ı şimdi alıyoruz (Order
        // construct'ından önce). snapshotAt timestamp'i Order
        // creation anına yakın (saniye seviyesinde aynı).
        $deliverySnapshot = $deliveryAddress->toSnapshot();
        $billingSnapshot = $billingAddress->toSnapshot();
        $sameBilling = $billingAddress->getId()->value() === $deliveryAddress->getId()->value();

        $order = new Order(
            id: $orderId,
            orderNumber: $orderNumber,
            userId: $userId,
            status: OrderStatus::PENDING,
            subtotal: $subtotal,
            total: $total,
            displayCurrency: $displayCurrency,
            displayTotal: $displayTotal,
            exchangeRateSnapshot: $exchangeRateUsed,
            notes: $command->notes,
            items: $orderItems,
            deliveryAddressSnapshot: $deliverySnapshot,
            billingAddressSnapshot: $billingSnapshot,
            deliveryAddressId: $deliveryAddress->getId()->value(),
            billingAddressId: $billingAddress->getId()->value(),
        );

        $this->orderRepository->save($order);

        $cart->markAsConverted($orderId);
        $this->cartRepository->save($cart);

        $this->auditLogger->log(new AuditEvent(
            actor: 'userId:' . $command->getUserId(),
            actorIp: $command->getUserIp(),
            action: 'sales.order_place',
            resource: 'order:' . $orderId->value(),
            module: 'sales',
            result: 'success',
            correlationId: $command->getCorrelationId(),
            metadata: [
                'orderNumber' => $orderNumber,
                'subtotal' => $subtotal->amount,
                'displayCurrency' => $displayCurrency->value,
                'displayTotal' => $displayTotal->amount,
                'itemCount' => count($orderItems),
                'deliveryCity' => $deliverySnapshot['city'],
                'deliveryDistrict' => $deliverySnapshot['district'],
                'sameBillingAddress' => $sameBilling,
            ],
        ));

        return OrderDTO::fromEntity($order);
    }

    /**
     * Build the in-memory rate table CurrencyConverter expects.
     * Base parameter dinamik — cart'ın currency'sine göre değişir.
     *
     * @return array<string, ExchangeRate>
     */
    private function loadRateMap(Currency $base): array
    {
        $rates = $this->exchangeRateRepository->loadAllLatest($base);
        $map = [];
        foreach ($rates as $rate) {
            $map[$rate->pairKey()] = $rate;
        }
        return $map;
    }
}
