<?php

declare(strict_types=1);

namespace Domain\Identity\Enums;

enum UserRole: string
{
    case CUSTOMER = 'customer';
    case ADMIN = 'admin';

    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }

    public function isCustomer(): bool
    {
        return $this === self::CUSTOMER;
    }
}
