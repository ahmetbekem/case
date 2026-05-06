<?php

declare(strict_types=1);

namespace Application\Sales\Commands;

use Application\Shared\Abstracts\AbstractCommand;

/**
 * ADR-017: Login/Register başarıyla tamamlandıktan sonra, eğer
 * istemcide guest_cart_token cookie varsa LoginUser/RegisterUser
 * handler'ı içinden tetiklenir. Guest cart'ın user'a transfer veya
 * mevcut user cart ile merge edilmesini sağlar.
 *
 * Idempotent: guest cart yoksa no-op (bu da audit'lenir).
 */
final readonly class MergeGuestCartCommand extends AbstractCommand
{
    public function __construct(
        string $userId,
        public string $guestSessionId,
        string $correlationId,
        string $userIp,
    ) {
        parent::__construct($userId, $correlationId, $userIp);
    }

    public function getCommandName(): string
    {
        return 'MergeGuestCart';
    }
}
