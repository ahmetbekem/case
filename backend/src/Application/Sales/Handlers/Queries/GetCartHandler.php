<?php

declare(strict_types=1);

namespace Application\Sales\Handlers\Queries;

use Application\Sales\DTOs\CartDTO;
use Application\Sales\Queries\GetCartQuery;
use Application\Shared\Abstracts\AbstractQueryHandler;
use Application\Shared\Contracts\QueryInterface;
use Domain\Sales\Contracts\CartRepositoryInterface;
use Domain\Sales\Entities\Cart;
use Domain\Sales\ValueObjects\GuestSessionId;
use Domain\Shared\ValueObjects\UserId;
use Psr\Log\LoggerInterface;

final class GetCartHandler extends AbstractQueryHandler
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        LoggerInterface $logger,
    ) {
        parent::__construct($logger);
    }

    public static function getHandledQueryClass(): string
    {
        return GetCartQuery::class;
    }

    protected function doHandle(QueryInterface $query): CartDTO
    {
        assert($query instanceof GetCartQuery);

        $cart = $query->isGuest()
            ? $this->cartRepository->findActiveCartForGuestSession(
                GuestSessionId::fromString((string) $query->guestSessionId)
            )
            : $this->cartRepository->findActiveCartForUser(
                UserId::fromString($query->getUserId())
            );

        // GET /cart on a fresh user/guest returns an empty cart instead
        // of 404 — simpler client UX (no special-case "first time" branch).
        if ($cart === null) {
            $cart = $query->isGuest()
                ? Cart::createForGuest(GuestSessionId::fromString((string) $query->guestSessionId))
                : Cart::createForUser(UserId::fromString($query->getUserId()));
        }

        return CartDTO::fromEntity($cart);
    }
}
