<?php

declare(strict_types=1);

namespace Domain\Sales\Contracts;

use Domain\Sales\Entities\Cart;
use Domain\Sales\ValueObjects\CartId;
use Domain\Sales\ValueObjects\GuestSessionId;
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
     * The single active cart per guest session (DB-enforced via partial
     * unique index uniq_carts_guest_active). ADR-017.
     */
    public function findActiveCartForGuestSession(GuestSessionId $sessionId): ?Cart;

    /**
     * Persist the entire aggregate — Cart row + every CartItem row.
     * Implementation is responsible for deleting items removed from the
     * in-memory aggregate (full sync).
     */
    public function save(Cart $cart): void;

    /**
     * Guest cart'ın owner'ını user'a transfer eder — guest_session_id
     * NULL'lanır, user_id set edilir, expires_at temizlenir. Login
     * sonrası user'ın boş cart'ı yoksa MergeGuestCartHandler bunu çağırır.
     */
    public function transferOwnership(CartId $cartId, UserId $newUserId): void;

    /**
     * Süresi geçmiş aktif guest cart'ları siler (cascade ile cart_items
     * de gider). Daily scheduled job çağırır. Silinen sayıyı döner.
     */
    public function deleteExpiredGuestCarts(): int;
}
