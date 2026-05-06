<?php

declare(strict_types=1);

namespace Application\Sales\Commands;

use Application\Shared\Abstracts\AbstractCommand;

/**
 * ADR-017: Cart komutları artık ya userId ya guestSessionId taşır.
 * Parent::userId actor identifier'ı olarak kullanılır (audit için):
 * authenticated user için UUID, guest için `guest:<sessionId>`.
 * Handler hangi yolu izleyeceğine $guestSessionId !== null ile karar
 * verir — UserId::fromString kullanmadan önce mutlaka kontrol etmeli.
 */
final readonly class AddItemToCartCommand extends AbstractCommand
{
    public function __construct(
        ?string $userId,
        public ?string $guestSessionId,
        string $correlationId,
        string $userIp,
        public string $productId,
        public int $quantity,
    ) {
        if ($userId === null && $guestSessionId === null) {
            throw new \InvalidArgumentException('AddItemToCartCommand requires userId or guestSessionId');
        }
        if ($userId !== null && $guestSessionId !== null) {
            throw new \InvalidArgumentException('AddItemToCartCommand cannot have both userId and guestSessionId');
        }
        parent::__construct($userId ?? 'guest:' . $guestSessionId, $correlationId, $userIp);
    }

    public function getCommandName(): string
    {
        return 'AddItemToCart';
    }

    public function isGuest(): bool
    {
        return $this->guestSessionId !== null;
    }
}
