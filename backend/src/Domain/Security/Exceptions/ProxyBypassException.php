<?php

declare(strict_types=1);

namespace Domain\Security\Exceptions;

use Domain\Shared\Constants\HttpStatus;
use Domain\Shared\Exceptions\DomainException;

/**
 * Raised by ProxyOriginMiddleware when an inbound request is missing the
 * X-Proxy-Secret header or carries a wrong value. In production the backend
 * is also network-isolated (ADR-014), so reaching this exception means
 * either someone got past the firewall or there is a misconfigured client.
 */
final class ProxyBypassException extends DomainException
{
    public function getHttpStatusCode(): int
    {
        return HttpStatus::FORBIDDEN;
    }

    public static function attempted(): self
    {
        return new self(
            message: 'Direct backend access is not allowed',
            errorCode: 'PROXY_BYPASS',
        );
    }
}
