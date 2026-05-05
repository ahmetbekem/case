<?php

declare(strict_types=1);

namespace Application\Sales\Handlers\Queries;

use Application\Sales\DTOs\CartDTO;
use Application\Sales\Queries\GetCartQuery;
use Application\Shared\Abstracts\AbstractQueryHandler;
use Application\Shared\Contracts\QueryInterface;
use Domain\Sales\Contracts\CartRepositoryInterface;
use Domain\Sales\Entities\Cart;
use Domain\Sales\ValueObjects\CartId;
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

        $userId = UserId::fromString($query->getUserId());
        $cart = $this->cartRepository->findActiveCartForUser($userId);

        // GET /cart on a fresh user returns an empty cart instead of 404 —
        // simpler client UX (no special-case "first time" branch).
        if ($cart === null) {
            $cart = new Cart(id: CartId::generate(), userId: $userId);
        }

        return CartDTO::fromEntity($cart);
    }
}
