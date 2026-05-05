<?php

declare(strict_types=1);

namespace Domain\Sales\Contracts;

use Domain\Sales\Entities\Cart;
use Domain\Sales\ValueObjects\CartId;
use Domain\Shared\Contracts\RepositoryInterface;
use Domain\Shared\ValueObjects\UserId;

/**
 * @extends RepositoryInterface<Cart, CartId>
 */
interface CartRepositoryInterface extends RepositoryInterface
{
    public function findById(CartId $id): ?Cart;

    /**
     * The single active cart per user (DB-enforced via partial unique
     * index uniq_carts_user_active).
     */
    public function findActiveCartForUser(UserId $userId): ?Cart;

    /**
     * Persist the entire aggregate — Cart row + every CartItem row.
     * Implementation is responsible for deleting items removed from the
     * in-memory aggregate (full sync).
     */
    public function save(Cart $cart): void;
}
