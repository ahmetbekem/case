<?php

declare(strict_types=1);

namespace Domain\Logging\ValueObjects;

/**
 * Structured audit event. Lives in Domain/Logging because it is the
 * payload of the framework-agnostic AuditLoggerInterface contract —
 * Infrastructure-side publishers translate it into the wire envelope
 * defined in LOGGING_SERVICE.md §5.3.
 *
 * Field names match the audit-* index template in LOGGING_SERVICE.md §6.2.
 */
final readonly class AuditEvent
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $actor,        // 'userId:<uuid>' | 'anonymous' | 'system'
        public string $actorIp,
        public string $action,       // dot-namespaced verb: identity.login, sales.order_place
        public string $resource,     // <type>:<id> e.g. 'user:abc-123', 'order:def-456'
        public string $module,       // identity | catalog | sales | currency | security
        public string $result,       // 'success' | 'failure'
        public string $correlationId,
        public ?string $userAgent = null,
        public array $metadata = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'actor' => $this->actor,
            'actorIp' => $this->actorIp,
            'action' => $this->action,
            'resource' => $this->resource,
            'module' => $this->module,
            'result' => $this->result,
            'correlationId' => $this->correlationId,
            'userAgent' => $this->userAgent,
            'metadata' => $this->metadata,
        ];
    }
}
