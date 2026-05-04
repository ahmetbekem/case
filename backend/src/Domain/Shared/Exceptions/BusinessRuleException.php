<?php

declare(strict_types=1);

namespace Domain\Shared\Exceptions;

use Domain\Shared\Constants\HttpStatus;

abstract class BusinessRuleException extends DomainException
{
    public function getHttpStatusCode(): int
    {
        return HttpStatus::CONFLICT;
    }
}
