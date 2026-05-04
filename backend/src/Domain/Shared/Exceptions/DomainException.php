<?php

declare(strict_types=1);

namespace Domain\Shared\Exceptions;

/**
 * Base of every domain-meaningful exception.
 *
 * Concrete subclasses live in each module (e.g. ProductNotFoundException),
 * are constructed via static factory methods (never `new`), and carry both
 * a machine-readable errorCode and structured context for logging.
 *
 * @see CODING_STANDARDS.md §5
 */
abstract class DomainException extends \RuntimeException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    abstract public function getHttpStatusCode(): int;
}
