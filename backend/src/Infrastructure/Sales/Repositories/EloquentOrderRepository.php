<?php

declare(strict_types=1);

namespace Infrastructure\Sales\Repositories;

use Domain\Sales\Contracts\OrderRepositoryInterface;
use Domain\Sales\Entities\Order;
use Domain\Sales\ValueObjects\OrderId;
use Domain\Shared\Contracts\ValueObjectInterface;
use Domain\Shared\ValueObjects\UserId;
use Illuminate\Support\Facades\DB;
use Infrastructure\Sales\Mappers\OrderItemMapper;
use Infrastructure\Sales\Models\OrderItemModel;
use Infrastructure\Sales\Models\OrderModel;
use Infrastructure\Shared\Abstracts\AbstractEloquentRepository;

/**
 * @extends AbstractEloquentRepository<Order, OrderId, OrderModel>
 *
 * Order items are immutable once persisted, so save() simply upserts
 * each one — no delete-orphans pass like the cart repo needs.
 */
final class EloquentOrderRepository extends AbstractEloquentRepository implements OrderRepositoryInterface
{
    public function __construct(
        private readonly OrderItemMapper $itemMapper,
    ) {
        parent::__construct($itemMapper);
    }

    protected function getModelClass(): string
    {
        return OrderModel::class;
    }

    protected function createId(string $id): ValueObjectInterface
    {
        return OrderId::fromString($id);
    }

    public function findById(OrderId $id): ?Order
    {
        $model = $this->query()->where('id', $id->value())->first();
        return $model ? $this->hydrate($model) : null;
    }

    public function findByUser(UserId $userId, int $page = 1, int $pageSize = 20): array
    {
        $models = $this->query()
            ->where('user_id', $userId->value())
            ->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $pageSize)
            ->take($pageSize)
            ->get();

        return $models->map(fn ($m) => $this->hydrate($m))->all();
    }

    public function countForUser(UserId $userId): int
    {
        return $this->query()->where('user_id', $userId->value())->count();
    }

    public function paginateAll(int $page = 1, int $pageSize = 20): array
    {
        $models = $this->query()
            ->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $pageSize)
            ->take($pageSize)
            ->get();

        return $models->map(fn ($m) => $this->hydrate($m))->all();
    }

    public function countAll(): int
    {
        return $this->query()->count();
    }

    public function save(Order $order): void
    {
        // updateOrInsert kendisi cast'i tetiklemediğinden JSONB array'leri
        // manuel json_encode etmek gerekiyor (full create/update üzerinden
        // gitmiyoruz çünkü write-aggregate flow'u updateOrInsert).
        $deliverySnapshot = $order->getDeliveryAddressSnapshot();
        $billingSnapshot = $order->getBillingAddressSnapshot();

        OrderModel::query()->updateOrInsert(
            ['id' => $order->getId()->value()],
            [
                'id' => $order->getId()->value(),
                'order_number' => $order->getOrderNumber(),
                'user_id' => $order->getUserId()->value(),
                'status' => $order->getStatus()->value,
                'subtotal' => $order->getSubtotal()->amount,
                'total' => $order->getTotal()->amount,
                'display_currency' => $order->getDisplayCurrency()->value,
                'display_total' => $order->getDisplayTotal()->amount,
                'exchange_rate_snapshot' => $order->getExchangeRateSnapshot(),
                'notes' => $order->getNotes(),
                'cancelled_at' => $order->getCancelledAt(),
                'cancelled_reason' => $order->getCancelledReason(),
                'completed_at' => $order->getCompletedAt(),
                'created_at' => $order->getCreatedAt(),
                'updated_at' => $order->getUpdatedAt(),
                // ADR-018 — JSONB snapshot + nullable FK
                'delivery_address' => $deliverySnapshot !== null ? json_encode($deliverySnapshot, JSON_UNESCAPED_UNICODE) : null,
                'billing_address' => $billingSnapshot !== null ? json_encode($billingSnapshot, JSON_UNESCAPED_UNICODE) : null,
                'delivery_address_id' => $order->getDeliveryAddressId(),
                'billing_address_id' => $order->getBillingAddressId(),
            ],
        );

        foreach ($order->getItems() as $item) {
            OrderItemModel::query()->updateOrInsert(
                ['id' => $item->getId()->value()],
                $this->itemMapper->toPersistence($item),
            );
        }
    }

    public function generateNumber(): string
    {
        $row = DB::select("SELECT nextval('sales.order_number_seq') AS seq");
        $seq = (int) $row[0]->seq;
        return 'ORD-' . date('Y') . '-' . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }

    private function hydrate(OrderModel $model): Order
    {
        $itemModels = OrderItemModel::query()
            ->where('order_id', $model->id)
            ->orderBy('created_at')
            ->get()
            ->all();

        $items = array_map(
            fn ($im) => $this->itemMapper->toEntity($im),
            $itemModels,
        );

        return Order::fromArray([
            'id' => $model->id,
            'orderNumber' => $model->order_number,
            'userId' => $model->user_id,
            'status' => $model->status,
            'subtotal' => (float) $model->subtotal,
            'total' => (float) $model->total,
            'currency' => 'TRY',  // base currency invariant
            'displayCurrency' => $model->display_currency,
            'displayTotal' => (float) $model->display_total,
            'exchangeRateSnapshot' => (float) $model->exchange_rate_snapshot,
            'notes' => $model->notes,
            'cancelledAt' => $model->cancelled_at?->format(\DateTimeInterface::ATOM),
            'cancelledReason' => $model->cancelled_reason,
            'completedAt' => $model->completed_at?->format(\DateTimeInterface::ATOM),
            'createdAt' => $model->created_at->format(\DateTimeInterface::ATOM),
            'updatedAt' => $model->updated_at?->format(\DateTimeInterface::ATOM),
            // ADR-018 — cast 'array' otomatik decode eder; null güvenlikli
            'deliveryAddress' => $model->delivery_address,
            'billingAddress' => $model->billing_address,
            'deliveryAddressId' => $model->delivery_address_id,
            'billingAddressId' => $model->billing_address_id,
        ], $items);
    }
}
