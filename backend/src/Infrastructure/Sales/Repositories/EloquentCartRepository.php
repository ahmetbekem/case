<?php

declare(strict_types=1);

namespace Infrastructure\Sales\Repositories;

use Domain\Sales\Contracts\CartRepositoryInterface;
use Domain\Sales\Entities\Cart;
use Domain\Sales\ValueObjects\CartId;
use Domain\Sales\ValueObjects\GuestSessionId;
use Domain\Shared\Contracts\ValueObjectInterface;
use Domain\Shared\ValueObjects\UserId;
use Infrastructure\Sales\Mappers\CartItemMapper;
use Infrastructure\Sales\Models\CartItemModel;
use Infrastructure\Sales\Models\CartModel;
use Infrastructure\Shared\Abstracts\AbstractEloquentRepository;

/**
 * @extends AbstractEloquentRepository<Cart, CartId, CartModel>
 *
 * Cart is a write-aggregate: every save() syncs the items collection —
 * deletes rows missing from the in-memory aggregate, upserts the rest.
 * Transactional consistency is the caller's job (handlers run inside
 * AbstractCommandHandler's transaction wrapper).
 *
 * ADR-017: Cart owner XOR (user_id veya guest_session_id, ikisi birden
 * değil). DB chk_carts_owner CHECK constraint'i ile garantilenir.
 */
final class EloquentCartRepository extends AbstractEloquentRepository implements CartRepositoryInterface
{
    public function __construct(
        private readonly CartItemMapper $itemMapper,
    ) {
        parent::__construct($itemMapper);
    }

    protected function getModelClass(): string
    {
        return CartModel::class;
    }

    protected function createId(string $id): ValueObjectInterface
    {
        return CartId::fromString($id);
    }

    public function findById(CartId $id): ?Cart
    {
        $cartModel = $this->query()->where('id', $id->value())->first();
        return $cartModel ? $this->hydrate($cartModel) : null;
    }

    public function findActiveCartForUser(UserId $userId): ?Cart
    {
        $cartModel = $this->query()
            ->where('user_id', $userId->value())
            ->where('status', Cart::STATUS_ACTIVE)
            ->first();

        return $cartModel ? $this->hydrate($cartModel) : null;
    }

    public function findActiveCartForGuestSession(GuestSessionId $sessionId): ?Cart
    {
        $cartModel = $this->query()
            ->where('guest_session_id', $sessionId->value())
            ->where('status', Cart::STATUS_ACTIVE)
            ->first();

        return $cartModel ? $this->hydrate($cartModel) : null;
    }

    public function save(Cart $cart): void
    {
        CartModel::query()->updateOrInsert(
            ['id' => $cart->getId()->value()],
            [
                'id' => $cart->getId()->value(),
                'user_id' => $cart->getUserId()?->value(),
                'guest_session_id' => $cart->getGuestSessionId()?->value(),
                'status' => $cart->getStatus(),
                'expires_at' => $cart->getExpiresAt(),
                'converted_to_order_id' => $cart->getConvertedToOrderId()?->value(),
                'created_at' => $cart->getCreatedAt(),
                'updated_at' => $cart->getUpdatedAt(),
            ],
        );

        // Reconcile items — delete rows missing from the aggregate
        $existingIds = CartItemModel::query()
            ->where('cart_id', $cart->getId()->value())
            ->pluck('id')
            ->all();
        $currentIds = array_map(
            fn ($item) => $item->getId()->value(),
            $cart->getItems(),
        );
        $toDelete = array_diff($existingIds, $currentIds);
        if ($toDelete !== []) {
            CartItemModel::query()->whereIn('id', $toDelete)->delete();
        }

        foreach ($cart->getItems() as $item) {
            CartItemModel::query()->updateOrInsert(
                ['id' => $item->getId()->value()],
                $this->itemMapper->toPersistence($item),
            );
        }
    }

    public function transferOwnership(CartId $cartId, UserId $newUserId): void
    {
        // Tek UPDATE — DB CHECK constraint XOR'u koruduğundan iki alanı
        // aynı anda güncellemek zorundayız (guest_session_id NULL +
        // user_id set + expires_at NULL).
        CartModel::query()
            ->where('id', $cartId->value())
            ->update([
                'user_id' => $newUserId->value(),
                'guest_session_id' => null,
                'expires_at' => null,
                'updated_at' => new \DateTimeImmutable(),
            ]);
    }

    public function deleteExpiredGuestCarts(): int
    {
        // Yalnızca aktif guest cart'lar — converted/abandoned'a dokunma
        // (analytics tarihçesi). cart_items FK ON DELETE CASCADE.
        return CartModel::query()
            ->whereNotNull('guest_session_id')
            ->where('status', Cart::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', new \DateTimeImmutable())
            ->delete();
    }

    private function hydrate(CartModel $cartModel): Cart
    {
        $itemModels = CartItemModel::query()
            ->where('cart_id', $cartModel->id)
            ->orderBy('created_at')
            ->get()
            ->all();

        $items = array_map(
            fn ($model) => $this->itemMapper->toEntity($model),
            $itemModels,
        );

        return Cart::fromArray([
            'id' => $cartModel->id,
            'userId' => $cartModel->user_id,
            'guestSessionId' => $cartModel->guest_session_id,
            'status' => $cartModel->status,
            'expiresAt' => $cartModel->expires_at?->format(\DateTimeInterface::ATOM),
            'convertedToOrderId' => $cartModel->converted_to_order_id,
            'createdAt' => $cartModel->created_at->format(\DateTimeInterface::ATOM),
            'updatedAt' => $cartModel->updated_at?->format(\DateTimeInterface::ATOM),
        ], $items);
    }
}
