<?php

declare(strict_types=1);

namespace Domain\Sales\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function isFinal(): bool
    {
        return $this === self::COMPLETED || $this === self::CANCELLED;
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }
}
