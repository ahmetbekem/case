<?php

declare(strict_types=1);

namespace Application\Sales\Handlers\Commands;

use Application\Sales\Commands\MergeGuestCartCommand;
use Application\Sales\DTOs\CartDTO;
use Application\Shared\Abstracts\AbstractCommandHandler;
use Application\Shared\Contracts\CommandInterface;
use Application\Shared\Contracts\TransactionInterface;
use Domain\Logging\Contracts\AuditLoggerInterface;
use Domain\Logging\ValueObjects\AuditEvent;
use Domain\Sales\Contracts\CartRepositoryInterface;
use Domain\Sales\Services\CartMerger;
use Domain\Sales\ValueObjects\GuestSessionId;
use Domain\Shared\ValueObjects\UserId;
use Psr\Log\LoggerInterface;

/**
 * ADR-017 Guest Cart merge.
 *
 * Senaryolar:
 *   1) Guest cart yok → no-op, null döner
 *   2) Guest cart var, user cart YOK → transferOwnership (en hafif yol)
 *   3) Guest cart var, user cart var → CartMerger.applyToUserCart
 *      (quantity'ler toplanır, oldest snapshot price korunur), guest
 *      cart "abandoned" status'una alınır
 *
 * Login/Register handler bu handler'ı try/catch içinde tetikler — auth
 * başarılı olmuş bir akışta merge fail'i login'i bozmamalı.
 */
final class MergeGuestCartHandler extends AbstractCommandHandler
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CartMerger $cartMerger,
        private readonly AuditLoggerInterface $auditLogger,
        TransactionInterface $transaction,
        LoggerInterface $logger,
    ) {
        parent::__construct($transaction, $logger);
    }

    public static function getHandledCommandClass(): string
    {
        return MergeGuestCartCommand::class;
    }

    protected function doHandle(CommandInterface $command): ?CartDTO
    {
        assert($command instanceof MergeGuestCartCommand);

        $userId = UserId::fromString($command->getUserId());
        $sessionId = GuestSessionId::fromString($command->guestSessionId);

        $guestCart = $this->cartRepository->findActiveCartForGuestSession($sessionId);

        if ($guestCart === null) {
            // Senaryo 1 — guest cart bulunamadı (cookie expired / hijack
            // / hiç eklemediği). Sessiz no-op.
            $this->logger->info('MergeGuestCart: no active guest cart for session', [
                'userId' => $userId->value(),
                'guestSessionId' => $sessionId->value(),
                'correlationId' => $command->getCorrelationId(),
            ]);
            return null;
        }

        $userCart = $this->cartRepository->findActiveCartForUser($userId);

        if ($userCart === null) {
            // Senaryo 2 — user'da aktif cart yok, guest cart'ı user'a devret
            $this->cartRepository->transferOwnership($guestCart->getId(), $userId);

            $this->auditLogger->log(new AuditEvent(
                actor: $userId->value(),
                actorIp: $command->getUserIp(),
                action: 'sales.cart_transferred_from_guest',
                resource: 'cart:' . $guestCart->getId()->value(),
                module: 'sales',
                result: 'success',
                correlationId: $command->getCorrelationId(),
                metadata: [
                    'guestSessionId' => $sessionId->value(),
                    'itemCount' => $guestCart->itemCount(),
                ],
            ));

            // Transfer sonrası taze hydrate — owner artık user
            $transferred = $this->cartRepository->findActiveCartForUser($userId);
            return $transferred !== null ? CartDTO::fromEntity($transferred) : null;
        }

        // Senaryo 3 — gerçek merge
        $guestItemCount = $guestCart->itemCount();
        $userItemCountBefore = $userCart->itemCount();

        $applied = $this->cartMerger->applyToUserCart($userCart, $guestCart);
        $this->cartRepository->save($userCart);

        // Guest cart'ı kapat — abandoned, böylece partial unique index
        // (uniq_carts_guest_active) yeni guest cart'lar için boşalır
        $guestCart->markAsAbandoned();
        $this->cartRepository->save($guestCart);

        $this->auditLogger->log(new AuditEvent(
            actor: $userId->value(),
            actorIp: $command->getUserIp(),
            action: 'sales.cart_merged_from_guest',
            resource: 'cart:' . $userCart->getId()->value(),
            module: 'sales',
            result: 'success',
            correlationId: $command->getCorrelationId(),
            metadata: [
                'guestSessionId' => $sessionId->value(),
                'guestItemCount' => $guestItemCount,
                'userItemCountBefore' => $userItemCountBefore,
                'mergeOpsApplied' => $applied,
            ],
        ));

        return CartDTO::fromEntity($userCart);
    }
}
