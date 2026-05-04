# 📊 Dehasoft Test Projesi - Logging Service Architecture

> **CRITICAL:** Bu doküman log servisinin (Node.js + RabbitMQ + Elasticsearch) tam mimarisini tanımlar. Laravel'deki integration pattern'leri ve Node.js consumer'ın yapısı bu dokümana uymak zorundadır.

**Version:** 1.0
**Stack:** Node.js 20+ (TypeScript), RabbitMQ 3.13+, Elasticsearch 8.x, Redis 7+
**Pattern:** Event-driven, async log shipping, fail-safe

---

## 📋 TABLE OF CONTENTS

1. [Why a Separate Logging Service](#1-why-a-separate-logging-service)
2. [Architecture Overview](#2-architecture-overview)
3. [Log Categories](#3-log-categories)
4. [Publish Strategy](#4-publish-strategy)
5. [RabbitMQ Topology](#5-rabbitmq-topology)
6. [Elasticsearch Index Strategy](#6-elasticsearch-index-strategy)
7. [Laravel Integration](#7-laravel-integration)
8. [Node.js Consumer Service](#8-nodejs-consumer-service)
9. [Log Schemas](#9-log-schemas)
10. [Failure Handling](#10-failure-handling)
11. [Repository & Directory Structure](#11-repository--directory-structure)
12. [Local Development](#12-local-development)
13. [Operational Concerns](#13-operational-concerns)

---

## 1. WHY A SEPARATE LOGGING SERVICE

### 1.1 Problem with Direct Logging

Eğer Laravel doğrudan Elasticsearch'e yazsaydı:

❌ Her HTTP request, ES'in cevap vermesini bekler (latency)
❌ ES down olursa app çöker
❌ ES'e bağımlılık her PHP worker'a yayılır (connection pool overhead)
❌ Bulk indexing yapılamaz (her log tek tek yazılır — verimsiz)
❌ Log şeması değişince Laravel'i deploy etmek gerekir

### 1.2 Solution: Decoupled Log Pipeline

```
Laravel  →  RabbitMQ  →  Node.js Consumer  →  Elasticsearch
   ↑           ↑                ↑                    ↑
fast pub   buffer/queue    bulk index       log storage
```

**Avantajlar:**
- ✅ Laravel sadece RabbitMQ'ya publish eder (ms altı)
- ✅ ES down olsa bile app çalışır (RabbitMQ buffer'lar)
- ✅ Node.js consumer batch insert yapar (1000x daha hızlı)
- ✅ Log şeması/transformasyonu değişimi sadece consumer redeploy'u
- ✅ Multi-source: ileride Next.js veya başka servisten de log alabilir

> **StkPro reference:** Aynı pattern StkPro'da kullanıldı — bu projede de aynı kalıbı uyguluyoruz.

---

## 2. ARCHITECTURE OVERVIEW

```
┌─────────────────────────────────────────────────────────────────┐
│                        LARAVEL APP                                │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │  AuditLogger    AppLogger    AccessLogger              │    │
│  │  (PSR-3 implementations)                                │    │
│  └────────┬─────────────┬──────────────┬───────────────────┘    │
│           │             │              │                          │
│      sync publish   queue dispatch  queue dispatch              │
│           │             │              │                          │
│           │     ┌───────▼──────────────▼───────┐               │
│           │     │  Laravel Queue (Redis)        │               │
│           │     │  - audit.log job (high prio)  │               │
│           │     │  - app.log job                │               │
│           │     │  - access.log job             │               │
│           │     └───────┬───────────────────────┘               │
│           │             │                                        │
│           │     ┌───────▼───────────────────────┐               │
│           │     │  Queue Worker                  │               │
│           │     │  (php artisan queue:work)     │               │
│           │     └───────┬───────────────────────┘               │
└───────────┼─────────────┼────────────────────────────────────────┘
            │             │
            ▼             ▼
┌─────────────────────────────────────────────────────────────────┐
│                        RABBITMQ                                   │
│  Exchange: logs.exchange (topic)                                 │
│  Queues:                                                          │
│    - logs.audit         (routing: log.audit.*)                   │
│    - logs.application   (routing: log.app.*)                     │
│    - logs.access        (routing: log.access.*)                  │
│  Dead Letter Exchange: logs.dlx                                  │
└────────┬────────────────┬───────────────────┬───────────────────┘
         │                │                   │
         ▼                ▼                   ▼
┌─────────────────────────────────────────────────────────────────┐
│              NODE.JS LOG CONSUMER SERVICE                         │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │  Consumer Workers (3 separate consumers)                   │  │
│  │   - AuditConsumer    (immediate index, no batching)       │  │
│  │   - AppConsumer      (batch: 100 docs / 5s)               │  │
│  │   - AccessConsumer   (batch: 500 docs / 5s)               │  │
│  └────────────────────────┬──────────────────────────────────┘  │
│                            │                                      │
│                            │ Bulk API                            │
└────────────────────────────┼──────────────────────────────────────┘
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                      ELASTICSEARCH                                │
│  Indices (with ILM policies):                                    │
│   - audit-YYYY.MM         (1 year retention)                     │
│   - app-YYYY.MM.DD         (30 days retention)                   │
│   - access-YYYY.MM.DD      (14 days retention)                   │
│  Index Templates: defined per category                           │
└─────────────────────────────────────────────────────────────────┘
```

---

## 3. LOG CATEGORIES

Üç farklı kategori, üç farklı SLA:

### 3.1 Audit Logs

**Ne zaman yazılır:** Compliance/forensic önemli olaylar
- User login/logout/register
- User role/permission değişiklikleri
- Order placement, cancellation
- Product CRUD (admin actions)
- Refresh token revoke (özellikle replay detection)
- Authentication failures (brute force detection)

**SLA:** **Loss intolerant** — bir audit log kaybolursa compliance ihlali. Sync publish + ack required.

**Retention:** 1 yıl (KVKK/GDPR uyumlu)

**Index:** `audit-YYYY.MM` (aylık rollover)

### 3.2 Application Logs

**Ne zaman yazılır:** Debugging, monitoring
- `LoggerInterface->info()` — normal flow
- `LoggerInterface->warning()` — anomaly ama hata değil
- `LoggerInterface->error()` — exception, business rule violation
- `LoggerInterface->critical()` — system failure

**SLA:** **Loss tolerant** — kaybolması kabul edilebilir (best effort)

**Retention:** 30 gün

**Index:** `app-YYYY.MM.DD` (günlük rollover)

### 3.3 Access Logs

**Ne zaman yazılır:** Her HTTP request (middleware)
- Method, URL, status, duration
- User ID, IP, User-Agent, Correlation ID
- Request body size, response size

**SLA:** **Loss tolerant** — high volume, kayıp önemli değil

**Retention:** 14 gün

**Index:** `access-YYYY.MM.DD`

---

## 4. PUBLISH STRATEGY

Hybrid yaklaşım — kritiklik seviyesine göre:

### 4.1 Audit: Sync Publish (Critical Path)

```
Handler → AuditLogger → RabbitMQ (publisher confirm) → ack
                              ↓
                       (RabbitMQ down?)
                              ↓
                       Fallback: write to local file + alert
```

- ✅ RabbitMQ publisher confirm mode (`mandatory=true, persistent=true`)
- ✅ Confirm timeout: 2 saniye
- ✅ Confirm gelmezse: local fallback file (`storage/logs/audit-fallback.log`) + critical alert
- ✅ Audit logs ASLA kaybolmamalı

### 4.2 Application: Async via Queue

```
Handler → AppLogger → Laravel Queue (Redis) → Worker → RabbitMQ
```

- ✅ `dispatch(new PublishAppLogJob(...))` — async
- ✅ Redis queue olarak `logs` named queue
- ✅ Worker batch processing (10 job at a time)
- ✅ RabbitMQ down olursa → Redis queue'da birikir, retry edilir

### 4.3 Access: Async via Queue (terminate hook)

```
Request → ... → Response
                  ↓
             terminate() middleware
                  ↓
             dispatch(new PublishAccessLogJob)
                  ↓
             Queue → Worker → RabbitMQ
```

- ✅ `terminate()` middleware — response gönderildikten SONRA çalışır, kullanıcı beklemez
- ✅ Yine async queue üzerinden RabbitMQ'ya

### 4.4 Why Hybrid

| Aspect | Sync (Audit) | Async (App + Access) |
|--------|-------------|---------------------|
| Latency impact | +5-20ms | ~0ms |
| Loss tolerance | ZERO | Acceptable |
| Implementation | Direct RabbitMQ publish | Laravel Queue + Worker |
| Volume | Low (~100/min) | High (~1000+/min) |

---

## 5. RABBITMQ TOPOLOGY

### 5.1 Exchange & Queues

```
Exchange: logs.exchange
  Type: topic
  Durable: true

Queues (all durable, persistent messages):
┌─────────────────────────────────────────────────────────┐
│ Queue: logs.audit                                        │
│ Routing Pattern: log.audit.*                            │
│ DLX: logs.dlx                                            │
│ Max Length: 100,000                                      │
│ TTL: 7 days (failsafe — should be consumed immediately)  │
└─────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────┐
│ Queue: logs.application                                  │
│ Routing Pattern: log.app.*                              │
│ DLX: logs.dlx                                            │
│ Max Length: 1,000,000                                    │
│ TTL: 24 hours                                            │
└─────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────┐
│ Queue: logs.access                                       │
│ Routing Pattern: log.access.*                           │
│ DLX: logs.dlx                                            │
│ Max Length: 5,000,000                                    │
│ TTL: 12 hours                                            │
└─────────────────────────────────────────────────────────┘

Dead Letter Exchange: logs.dlx (fanout)
  Queue: logs.dlq
    - All failed messages land here
    - Manual inspection / reprocessing
```

### 5.2 Routing Keys

| Routing Key | Source | Example |
|-------------|--------|---------|
| `log.audit.identity.login` | Identity module | Login success/fail |
| `log.audit.identity.refresh_revoke` | | Refresh token revoke |
| `log.audit.catalog.product_create` | Catalog | Admin created product |
| `log.audit.sales.order_place` | Sales | Order placed |
| `log.audit.sales.order_cancel` | Sales | Order cancelled |
| `log.app.identity.error` | App logs | Any error |
| `log.app.sales.warning` | | Stock low warning |
| `log.access.http` | Middleware | All HTTP requests |

### 5.3 Message Format (Common Envelope)

```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "category": "audit | app | access",
  "publishedAt": "2026-05-04T10:30:45.123Z",
  "source": "laravel-api",
  "version": "1.0",
  "payload": { /* category-specific */ }
}
```

---

## 6. ELASTICSEARCH INDEX STRATEGY

### 6.1 Index Naming

```
audit-2026.05            (audit, monthly)
app-2026.05.04           (application, daily)
access-2026.05.04        (access, daily)
```

**Aliases:**
- `audit-current` → audit-{current month}
- `app-current` → app-{today}
- `access-current` → access-{today}

### 6.2 Index Templates

**audit-* template:**
```json
{
  "index_patterns": ["audit-*"],
  "settings": {
    "number_of_shards": 1,
    "number_of_replicas": 1,
    "refresh_interval": "5s"
  },
  "mappings": {
    "properties": {
      "id": { "type": "keyword" },
      "publishedAt": { "type": "date" },
      "actor": { "type": "keyword" },
      "actorIp": { "type": "ip" },
      "action": { "type": "keyword" },
      "resource": { "type": "keyword" },
      "module": { "type": "keyword" },
      "result": { "type": "keyword" },
      "correlationId": { "type": "keyword" },
      "userAgent": { "type": "text", "fields": { "raw": { "type": "keyword" } } },
      "metadata": { "type": "object", "dynamic": true }
    }
  }
}
```

**app-* template:**
```json
{
  "index_patterns": ["app-*"],
  "settings": {
    "number_of_shards": 1,
    "number_of_replicas": 0,
    "refresh_interval": "10s"
  },
  "mappings": {
    "properties": {
      "id": { "type": "keyword" },
      "publishedAt": { "type": "date" },
      "level": { "type": "keyword" },
      "module": { "type": "keyword" },
      "message": { "type": "text" },
      "context": { "type": "object", "dynamic": true },
      "exception": {
        "properties": {
          "class": { "type": "keyword" },
          "message": { "type": "text" },
          "file": { "type": "keyword" },
          "line": { "type": "integer" },
          "trace": { "type": "text", "index": false }
        }
      },
      "correlationId": { "type": "keyword" },
      "userId": { "type": "keyword" }
    }
  }
}
```

**access-* template:**
```json
{
  "index_patterns": ["access-*"],
  "settings": {
    "number_of_shards": 1,
    "number_of_replicas": 0,
    "refresh_interval": "30s"
  },
  "mappings": {
    "properties": {
      "id": { "type": "keyword" },
      "publishedAt": { "type": "date" },
      "method": { "type": "keyword" },
      "path": { "type": "keyword" },
      "statusCode": { "type": "short" },
      "durationMs": { "type": "integer" },
      "userId": { "type": "keyword" },
      "ip": { "type": "ip" },
      "userAgent": { "type": "keyword" },
      "correlationId": { "type": "keyword" },
      "requestSize": { "type": "integer" },
      "responseSize": { "type": "integer" }
    }
  }
}
```

### 6.3 Index Lifecycle Management (ILM)

**audit-* policy:**
```json
{
  "phases": {
    "hot": { "actions": { "rollover": { "max_age": "30d" } } },
    "warm": { "min_age": "30d", "actions": { "shrink": { "number_of_shards": 1 } } },
    "delete": { "min_age": "365d", "actions": { "delete": {} } }
  }
}
```

**app-* policy:**
- Hot: 7 days
- Delete: 30 days

**access-* policy:**
- Hot: 3 days
- Delete: 14 days

---

## 7. LARAVEL INTEGRATION

### 7.1 Module Structure

Logging için Laravel'de yeni bir modül DEĞİL, **Infrastructure cross-cutting concern**:

```
src/Infrastructure/Logging/
├── Contracts/
│   └── (no contracts - Domain only)
├── Audit/
│   ├── AuditEvent.php                    # Value object for an audit event
│   ├── AuditLogger.php                   # Sync publisher
│   └── AuditFallbackWriter.php           # Local file fallback
├── Application/
│   └── AppLogger.php                     # PSR-3 implementation
├── Access/
│   └── AccessLogMiddleware.php
├── Publishing/
│   ├── RabbitMqPublisher.php             # Sync publisher
│   ├── QueuedLogPublisher.php            # Async via Laravel Queue
│   └── Jobs/
│       ├── PublishAppLogJob.php
│       └── PublishAccessLogJob.php
└── Config/
    └── (read from config/logging-service.php)

src/Domain/Logging/
└── Contracts/
    ├── AuditLoggerInterface.php          # Used by handlers
    └── (no AppLogger - PSR-3 LoggerInterface used directly)
```

### 7.2 Audit Logger Interface (Domain)

```php
<?php

declare(strict_types=1);

namespace Domain\Logging\Contracts;

interface AuditLoggerInterface
{
    public function log(AuditEvent $event): void;
}
```

### 7.3 Audit Event

```php
<?php

declare(strict_types=1);

namespace Infrastructure\Logging\Audit;

final readonly class AuditEvent
{
    public function __construct(
        public string $actor,             // userId or 'system' or 'anonymous'
        public string $actorIp,
        public string $action,            // 'product.create', 'order.place'
        public string $resource,          // 'product:uuid', 'order:uuid'
        public string $module,            // 'catalog', 'sales'
        public string $result,            // 'success', 'failure'
        public string $correlationId,
        public ?string $userAgent = null,
        public array $metadata = [],
    ) {}

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
```

### 7.4 Audit Logger Implementation (Sync)

```php
<?php

declare(strict_types=1);

namespace Infrastructure\Logging\Audit;

use Domain\Logging\Contracts\AuditLoggerInterface;
use Infrastructure\Logging\Publishing\RabbitMqPublisher;
use Psr\Log\LoggerInterface;

final class AuditLogger implements AuditLoggerInterface
{
    public function __construct(
        private readonly RabbitMqPublisher $publisher,
        private readonly AuditFallbackWriter $fallback,
        private readonly LoggerInterface $internalLogger,
    ) {}

    public function log(AuditEvent $event): void
    {
        $envelope = [
            'id' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'category' => 'audit',
            'publishedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339_EXTENDED),
            'source' => 'laravel-api',
            'version' => '1.0',
            'payload' => $event->toArray(),
        ];

        $routingKey = "log.audit.{$event->module}." . str_replace('.', '_', $event->action);

        try {
            $confirmed = $this->publisher->publishWithConfirm(
                exchange: 'logs.exchange',
                routingKey: $routingKey,
                payload: $envelope,
                timeoutMs: 2000,
            );

            if (!$confirmed) {
                $this->fallback->write($envelope);
                $this->internalLogger->critical('Audit log not confirmed by RabbitMQ', [
                    'auditId' => $envelope['id'],
                ]);
            }
        } catch (\Throwable $e) {
            $this->fallback->write($envelope);
            $this->internalLogger->critical('Audit log publish failed', [
                'auditId' => $envelope['id'],
                'error' => $e->getMessage(),
            ]);
        }
    }
}
```

### 7.5 PSR-3 Application Logger

Laravel'in monolog kanal yapılandırması — özel `rabbitmq` channel:

```php
// config/logging.php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['daily', 'rabbitmq'],
    ],

    'rabbitmq' => [
        'driver' => 'custom',
        'via' => \Infrastructure\Logging\Application\RabbitMqLogChannel::class,
        'level' => env('LOG_LEVEL', 'info'),
    ],

    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel.log'),
        'days' => 7,
    ],
],
```

```php
<?php

declare(strict_types=1);

namespace Infrastructure\Logging\Application;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

final class RabbitMqLogHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly QueuedLogPublisher $publisher,
        $level = \Monolog\Level::Info,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $envelope = [
            'id' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'category' => 'app',
            'publishedAt' => $record->datetime->format(\DateTimeInterface::RFC3339_EXTENDED),
            'source' => 'laravel-api',
            'version' => '1.0',
            'payload' => [
                'level' => $record->level->getName(),
                'module' => $record->context['module'] ?? null,
                'message' => $record->message,
                'context' => $this->sanitizeContext($record->context),
                'exception' => $this->extractException($record->context),
                'correlationId' => $record->context['correlationId'] ?? null,
                'userId' => $record->context['userId'] ?? null,
            ],
        ];

        // Async dispatch — never blocks the request
        $this->publisher->dispatchAsync(
            routingKey: "log.app.{$envelope['payload']['module']}." . strtolower($record->level->getName()),
            envelope: $envelope,
        );
    }

    private function sanitizeContext(array $context): array
    {
        // Remove sensitive keys, large objects
        unset($context['password'], $context['token'], $context['authorization']);
        return $context;
    }

    private function extractException(array $context): ?array
    {
        if (!isset($context['exception']) || !$context['exception'] instanceof \Throwable) {
            return null;
        }
        $e = $context['exception'];
        return [
            'class' => $e::class,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];
    }
}
```

### 7.6 Access Log Middleware

```php
<?php

declare(strict_types=1);

namespace Infrastructure\Logging\Access;

use Closure;
use Illuminate\Http\Request;
use Infrastructure\Logging\Publishing\QueuedLogPublisher;
use Symfony\Component\HttpFoundation\Response;

final class AccessLogMiddleware
{
    public function __construct(
        private readonly QueuedLogPublisher $publisher,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $response = $next($request);
        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        // Defer to terminate() — runs after response is sent
        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        $envelope = [
            'id' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'category' => 'access',
            'publishedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339_EXTENDED),
            'source' => 'laravel-api',
            'version' => '1.0',
            'payload' => [
                'method' => $request->getMethod(),
                'path' => $request->getPathInfo(),
                'statusCode' => $response->getStatusCode(),
                'durationMs' => $request->attributes->get('duration_ms', 0),
                'userId' => $request->attributes->get('user_id'),
                'ip' => $request->ip(),
                'userAgent' => $request->userAgent(),
                'correlationId' => $request->headers->get('X-Correlation-Id'),
                'requestSize' => (int) $request->headers->get('Content-Length', 0),
                'responseSize' => strlen($response->getContent() ?: ''),
            ],
        ];

        $this->publisher->dispatchAsync(
            routingKey: 'log.access.http',
            envelope: $envelope,
        );
    }
}
```

### 7.7 Service Provider Registration

```php
// app/Providers/LoggingServiceProvider.php
<?php

declare(strict_types=1);

namespace App\Providers;

use Domain\Logging\Contracts\AuditLoggerInterface;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Logging\Audit\AuditFallbackWriter;
use Infrastructure\Logging\Audit\AuditLogger;
use Infrastructure\Logging\Publishing\QueuedLogPublisher;
use Infrastructure\Logging\Publishing\RabbitMqPublisher;

final class LoggingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RabbitMqPublisher::class);
        $this->app->singleton(QueuedLogPublisher::class);
        $this->app->singleton(AuditFallbackWriter::class);

        $this->app->bind(AuditLoggerInterface::class, AuditLogger::class);
    }
}
```

### 7.8 Usage in Handlers

```php
// In a handler:
final class PlaceOrderHandler extends AbstractCommandHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepo,
        private readonly CartRepositoryInterface $cartRepo,
        private readonly AuditLoggerInterface $auditLogger,    // ← inject
        TransactionInterface $transaction,
        LoggerInterface $logger,
    ) {
        parent::__construct($transaction, $logger);
    }

    protected function doHandle(CommandInterface $command): OrderDTO
    {
        // ... business logic ...

        // Application log (async, best effort)
        $this->logger->info('Order placed', [
            'module' => 'sales',
            'orderId' => $order->getId()->value(),
            'userId' => $userId->value(),
            'correlationId' => $command->getCorrelationId(),
        ]);

        // Audit log (sync, critical)
        $this->auditLogger->log(new AuditEvent(
            actor: $userId->value(),
            actorIp: $command->getUserIp(),
            action: 'order.place',
            resource: 'order:' . $order->getId()->value(),
            module: 'sales',
            result: 'success',
            correlationId: $command->getCorrelationId(),
            metadata: [
                'total' => $order->getTotal(),
                'currency' => $order->getDisplayCurrency(),
                'itemCount' => count($order->getItems()),
            ],
        ));

        return OrderDTO::fromEntity($order);
    }
}
```

---

## 8. NODE.JS CONSUMER SERVICE

### 8.1 Project Setup

```
log-service/
├── src/
│   ├── consumers/
│   │   ├── AuditConsumer.ts            # Immediate index
│   │   ├── ApplicationConsumer.ts       # Batch 100/5s
│   │   └── AccessConsumer.ts            # Batch 500/5s
│   ├── elasticsearch/
│   │   ├── client.ts                    # ES client setup
│   │   ├── bulkIndexer.ts               # Bulk insert helper
│   │   └── indexResolver.ts             # Calculate target index name
│   ├── rabbitmq/
│   │   ├── connection.ts                # Connection management
│   │   ├── channelFactory.ts
│   │   └── reconnect.ts                 # Auto-reconnect logic
│   ├── schemas/
│   │   ├── envelope.ts                  # Common envelope type
│   │   ├── auditPayload.ts
│   │   ├── applicationPayload.ts
│   │   └── accessPayload.ts
│   ├── lib/
│   │   ├── logger.ts                    # Internal logger (pino)
│   │   ├── metrics.ts                   # Prometheus exporter (optional)
│   │   └── shutdown.ts                  # Graceful shutdown
│   ├── config/
│   │   └── env.ts                       # Env variable parsing (zod)
│   └── index.ts                          # Entry point
├── tests/
│   ├── consumers/
│   ├── elasticsearch/
│   └── schemas/
├── package.json
├── tsconfig.json
├── Dockerfile
├── .env.example
└── README.md
```

### 8.2 Stack & Dependencies

```json
{
  "dependencies": {
    "amqplib": "^0.10.0",
    "@elastic/elasticsearch": "^8.13.0",
    "zod": "^3.22.0",
    "pino": "^9.0.0",
    "dotenv": "^16.4.0"
  },
  "devDependencies": {
    "typescript": "^5.4.0",
    "@types/node": "^20.10.0",
    "@types/amqplib": "^0.10.0",
    "vitest": "^1.4.0",
    "tsx": "^4.7.0"
  }
}
```

### 8.3 Consumer Pattern

```typescript
// src/consumers/ApplicationConsumer.ts
import { Channel } from 'amqplib';
import { Client as ESClient } from '@elastic/elasticsearch';
import { logger } from '../lib/logger';
import { resolveAppIndex } from '../elasticsearch/indexResolver';
import { applicationPayloadSchema } from '../schemas/applicationPayload';
import { envelopeSchema } from '../schemas/envelope';

const BATCH_SIZE = 100;
const FLUSH_INTERVAL_MS = 5000;

export class ApplicationConsumer {
  private buffer: Array<{ index: string; doc: any; deliveryTag: bigint }> = [];
  private flushTimer: NodeJS.Timeout | null = null;

  constructor(
    private readonly channel: Channel,
    private readonly esClient: ESClient,
  ) {}

  async start(): Promise<void> {
    await this.channel.prefetch(BATCH_SIZE * 2);

    await this.channel.consume(
      'logs.application',
      (msg) => {
        if (!msg) return;
        this.handleMessage(msg);
      },
      { noAck: false },
    );

    this.scheduleFlush();
    logger.info('ApplicationConsumer started');
  }

  private handleMessage(msg: any): void {
    try {
      const raw = JSON.parse(msg.content.toString());
      const envelope = envelopeSchema.parse(raw);
      const payload = applicationPayloadSchema.parse(envelope.payload);

      const index = resolveAppIndex(new Date(envelope.publishedAt));
      const doc = {
        id: envelope.id,
        publishedAt: envelope.publishedAt,
        ...payload,
      };

      this.buffer.push({
        index,
        doc,
        deliveryTag: msg.fields.deliveryTag,
      });

      if (this.buffer.length >= BATCH_SIZE) {
        this.flush().catch((err) => logger.error({ err }, 'Flush failed'));
      }
    } catch (err) {
      logger.error({ err }, 'Failed to parse app log message');
      this.channel.nack(msg, false, false); // Send to DLQ
    }
  }

  private async flush(): Promise<void> {
    if (this.buffer.length === 0) return;

    const batch = this.buffer.splice(0, this.buffer.length);

    const operations = batch.flatMap((item) => [
      { index: { _index: item.index } },
      item.doc,
    ]);

    try {
      const response = await this.esClient.bulk({ operations });

      if (response.errors) {
        const failedItems = response.items.filter(
          (item: any) => item.index?.error,
        );
        logger.warn(
          { failedCount: failedItems.length, total: batch.length },
          'Some documents failed to index',
        );
      }

      // Ack all messages in this batch
      const lastTag = batch[batch.length - 1].deliveryTag;
      this.channel.ack({ fields: { deliveryTag: lastTag } } as any, true);

      logger.debug({ count: batch.length }, 'Batch indexed');
    } catch (err) {
      logger.error({ err, batchSize: batch.length }, 'Bulk index failed');
      // Nack all — they'll be redelivered
      const lastTag = batch[batch.length - 1].deliveryTag;
      this.channel.nack({ fields: { deliveryTag: lastTag } } as any, true, true);
    }
  }

  private scheduleFlush(): void {
    this.flushTimer = setInterval(() => {
      this.flush().catch((err) => logger.error({ err }, 'Scheduled flush failed'));
    }, FLUSH_INTERVAL_MS);
  }

  async stop(): Promise<void> {
    if (this.flushTimer) clearInterval(this.flushTimer);
    await this.flush();
    logger.info('ApplicationConsumer stopped');
  }
}
```

### 8.4 Audit Consumer (Immediate Index)

```typescript
// src/consumers/AuditConsumer.ts
// Same pattern but BATCH_SIZE = 1, FLUSH_INTERVAL_MS = 100
// Audit logs are critical — no batching delay
```

### 8.5 Elasticsearch Index Resolver

```typescript
// src/elasticsearch/indexResolver.ts
export function resolveAuditIndex(date: Date): string {
  const yyyy = date.getUTCFullYear();
  const mm = String(date.getUTCMonth() + 1).padStart(2, '0');
  return `audit-${yyyy}.${mm}`;
}

export function resolveAppIndex(date: Date): string {
  const yyyy = date.getUTCFullYear();
  const mm = String(date.getUTCMonth() + 1).padStart(2, '0');
  const dd = String(date.getUTCDate()).padStart(2, '0');
  return `app-${yyyy}.${mm}.${dd}`;
}

export function resolveAccessIndex(date: Date): string {
  const yyyy = date.getUTCFullYear();
  const mm = String(date.getUTCMonth() + 1).padStart(2, '0');
  const dd = String(date.getUTCDate()).padStart(2, '0');
  return `access-${yyyy}.${mm}.${dd}`;
}
```

### 8.6 Connection Resilience

```typescript
// src/rabbitmq/reconnect.ts
import * as amqp from 'amqplib';
import { logger } from '../lib/logger';

export async function connectWithRetry(url: string, maxAttempts = Infinity): Promise<amqp.Connection> {
  let attempt = 0;
  while (attempt < maxAttempts) {
    try {
      const conn = await amqp.connect(url, { heartbeat: 30 });
      logger.info('Connected to RabbitMQ');

      conn.on('error', (err) => logger.error({ err }, 'RabbitMQ connection error'));
      conn.on('close', () => {
        logger.warn('RabbitMQ connection closed, reconnecting...');
        setTimeout(() => connectWithRetry(url), 5000);
      });

      return conn;
    } catch (err) {
      attempt++;
      const backoff = Math.min(1000 * Math.pow(2, attempt), 60000);
      logger.warn({ attempt, backoff }, 'RabbitMQ connection failed, retrying...');
      await new Promise((r) => setTimeout(r, backoff));
    }
  }
  throw new Error('Could not connect to RabbitMQ after max attempts');
}
```

### 8.7 Graceful Shutdown

```typescript
// src/lib/shutdown.ts
export function setupGracefulShutdown(consumers: Array<{ stop(): Promise<void> }>) {
  const shutdown = async (signal: string) => {
    logger.info({ signal }, 'Shutting down gracefully...');
    for (const consumer of consumers) {
      try {
        await consumer.stop();
      } catch (err) {
        logger.error({ err }, 'Error stopping consumer');
      }
    }
    process.exit(0);
  };

  process.on('SIGTERM', () => shutdown('SIGTERM'));
  process.on('SIGINT', () => shutdown('SIGINT'));
}
```

---

## 9. LOG SCHEMAS

### 9.1 Common Envelope (Zod)

```typescript
// src/schemas/envelope.ts
import { z } from 'zod';

export const envelopeSchema = z.object({
  id: z.string().uuid(),
  category: z.enum(['audit', 'app', 'access']),
  publishedAt: z.string().datetime(),
  source: z.string(),
  version: z.string(),
  payload: z.record(z.any()),
});

export type Envelope = z.infer<typeof envelopeSchema>;
```

### 9.2 Audit Payload

```typescript
// src/schemas/auditPayload.ts
import { z } from 'zod';

export const auditPayloadSchema = z.object({
  actor: z.string(),
  actorIp: z.string().nullable().optional(),
  action: z.string(),
  resource: z.string(),
  module: z.string(),
  result: z.enum(['success', 'failure']),
  correlationId: z.string(),
  userAgent: z.string().nullable().optional(),
  metadata: z.record(z.any()).default({}),
});
```

### 9.3 Application Payload

```typescript
// src/schemas/applicationPayload.ts
import { z } from 'zod';

export const applicationPayloadSchema = z.object({
  level: z.enum(['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL']),
  module: z.string().nullable(),
  message: z.string(),
  context: z.record(z.any()).default({}),
  exception: z.object({
    class: z.string(),
    message: z.string(),
    file: z.string(),
    line: z.number(),
    trace: z.string(),
  }).nullable().optional(),
  correlationId: z.string().nullable().optional(),
  userId: z.string().nullable().optional(),
});
```

### 9.4 Access Payload

```typescript
// src/schemas/accessPayload.ts
import { z } from 'zod';

export const accessPayloadSchema = z.object({
  method: z.string(),
  path: z.string(),
  statusCode: z.number().int(),
  durationMs: z.number().int(),
  userId: z.string().nullable().optional(),
  ip: z.string(),
  userAgent: z.string().nullable().optional(),
  correlationId: z.string().nullable().optional(),
  requestSize: z.number().int().default(0),
  responseSize: z.number().int().default(0),
});
```

---

## 10. FAILURE HANDLING

### 10.1 Failure Scenarios

| Scenario | Mitigation |
|----------|-----------|
| RabbitMQ down (Laravel publishing) | Audit: fallback file + alert. App/Access: Redis queue retains messages |
| Redis down (Laravel queue) | Logs lost (acceptable for app/access) |
| Node consumer down | RabbitMQ buffers messages (queue max length) |
| Elasticsearch down | Consumer retries with exponential backoff, messages stay in queue |
| Bad message (parse error) | Routed to DLQ, not retried |
| Slow ES indexing | RabbitMQ queue grows; alert at 80% capacity |

### 10.2 Dead Letter Queue Handling

DLQ'daki mesajlar manuel inceleme için tutulur. Periyodik bir CLI komutu DLQ'yu okuyup raporlar:

```bash
node dist/cli/inspect-dlq.js --limit=100
```

### 10.3 Audit Fallback Recovery

`storage/logs/audit-fallback.log` dosyası varsa ve RabbitMQ erişilebilirse:

```bash
php artisan logging:replay-audit-fallback
```

Komut dosyayı okur, RabbitMQ'ya re-publish eder, başarılı olanları siler.

---

## 11. REPOSITORY & DIRECTORY STRUCTURE

Monorepo'da yeni servis:

```
testproject/
├── backend/                       # Laravel
├── frontend/                      # Next.js
├── log-service/                   # ⭐ NEW: Node.js logging consumer
│   └── (yapı yukarıda)
├── infrastructure/
│   ├── elasticsearch/
│   │   ├── templates/             # Index templates JSON
│   │   ├── ilm-policies/          # ILM policy definitions
│   │   └── bootstrap.sh           # Initial setup script
│   └── rabbitmq/
│       ├── definitions.json       # Exchanges, queues, bindings
│       └── bootstrap.sh
├── docker-compose.yml             # All services
└── docs/
    ├── PROJECT_STRUCTURE.md
    ├── DATABASE_SCHEMA.md
    ├── CODING_STANDARDS.md
    └── LOGGING_SERVICE.md         # ⭐ This file
```

---

## 12. LOCAL DEVELOPMENT

### 12.1 docker-compose Services

```yaml
services:
  postgres:
    image: postgres:15
    # ...

  redis:
    image: redis:7-alpine
    # ...

  rabbitmq:
    image: rabbitmq:3.13-management-alpine
    ports:
      - "5672:5672"
      - "15672:15672"     # Management UI: http://localhost:15672
    environment:
      RABBITMQ_DEFAULT_USER: admin
      RABBITMQ_DEFAULT_PASS: admin
    volumes:
      - rabbitmq-data:/var/lib/rabbitmq
      - ./infrastructure/rabbitmq/definitions.json:/etc/rabbitmq/definitions.json
      - ./infrastructure/rabbitmq/rabbitmq.conf:/etc/rabbitmq/rabbitmq.conf

  elasticsearch:
    image: docker.elastic.co/elasticsearch/elasticsearch:8.13.0
    environment:
      - discovery.type=single-node
      - xpack.security.enabled=false
      - ES_JAVA_OPTS=-Xms512m -Xmx512m
    ports:
      - "9200:9200"
    volumes:
      - elasticsearch-data:/usr/share/elasticsearch/data

  kibana:
    image: docker.elastic.co/kibana/kibana:8.13.0
    ports:
      - "5601:5601"
    environment:
      - ELASTICSEARCH_HOSTS=http://elasticsearch:9200
    depends_on:
      - elasticsearch

  log-service:
    build:
      context: ./log-service
    environment:
      - RABBITMQ_URL=amqp://admin:admin@rabbitmq:5672
      - ELASTICSEARCH_URL=http://elasticsearch:9200
      - LOG_LEVEL=info
    depends_on:
      rabbitmq:
        condition: service_healthy
      elasticsearch:
        condition: service_healthy

  backend:
    build: ./backend
    environment:
      - RABBITMQ_HOST=rabbitmq
      - RABBITMQ_PORT=5672
      - RABBITMQ_USER=admin
      - RABBITMQ_PASSWORD=admin
      - LOG_CHANNEL=stack
    depends_on:
      - postgres
      - redis
      - rabbitmq

  queue-worker:
    build: ./backend
    command: php artisan queue:work --queue=logs --tries=3
    depends_on:
      - redis
      - rabbitmq
```

### 12.2 RabbitMQ Bootstrap Definitions

```json
// infrastructure/rabbitmq/definitions.json
{
  "exchanges": [
    { "name": "logs.exchange", "vhost": "/", "type": "topic", "durable": true },
    { "name": "logs.dlx", "vhost": "/", "type": "fanout", "durable": true }
  ],
  "queues": [
    {
      "name": "logs.audit",
      "vhost": "/",
      "durable": true,
      "arguments": {
        "x-dead-letter-exchange": "logs.dlx",
        "x-max-length": 100000,
        "x-message-ttl": 604800000
      }
    },
    {
      "name": "logs.application",
      "vhost": "/",
      "durable": true,
      "arguments": {
        "x-dead-letter-exchange": "logs.dlx",
        "x-max-length": 1000000,
        "x-message-ttl": 86400000
      }
    },
    {
      "name": "logs.access",
      "vhost": "/",
      "durable": true,
      "arguments": {
        "x-dead-letter-exchange": "logs.dlx",
        "x-max-length": 5000000,
        "x-message-ttl": 43200000
      }
    },
    { "name": "logs.dlq", "vhost": "/", "durable": true }
  ],
  "bindings": [
    { "source": "logs.exchange", "destination": "logs.audit", "destination_type": "queue", "routing_key": "log.audit.#" },
    { "source": "logs.exchange", "destination": "logs.application", "destination_type": "queue", "routing_key": "log.app.#" },
    { "source": "logs.exchange", "destination": "logs.access", "destination_type": "queue", "routing_key": "log.access.#" },
    { "source": "logs.dlx", "destination": "logs.dlq", "destination_type": "queue", "routing_key": "" }
  ]
}
```

### 12.3 Elasticsearch Bootstrap

```bash
# infrastructure/elasticsearch/bootstrap.sh
#!/usr/bin/env bash
set -e

ES_URL="${ES_URL:-http://localhost:9200}"

echo "Creating index templates..."
for template in audit app access; do
  curl -X PUT "$ES_URL/_index_template/${template}-template" \
    -H 'Content-Type: application/json' \
    -d "@templates/${template}.json"
done

echo "Creating ILM policies..."
for policy in audit app access; do
  curl -X PUT "$ES_URL/_ilm/policy/${policy}-policy" \
    -H 'Content-Type: application/json' \
    -d "@ilm-policies/${policy}.json"
done

echo "Done!"
```

---

## 13. OPERATIONAL CONCERNS

### 13.1 Monitoring

**Metrics to track:**
- RabbitMQ queue depth per queue (alert > 80% capacity)
- Consumer message processing rate
- Elasticsearch indexing rate
- DLQ size (alert > 0)
- Audit fallback file size (alert > 0)
- Consumer lag (publishedAt vs indexed time)

**Tools:**
- RabbitMQ Management UI (built-in)
- Kibana (logs themselves)
- Optional: Prometheus + Grafana

### 13.2 Sensitive Data

**Asla loglanmaz:**
- Plaintext password
- JWT/refresh token (sadece hash veya partial)
- Kredi kartı bilgisi
- Kişisel veriler (KVKK kapsamında: TCKN, adres detayı vb.)

`RabbitMqLogHandler::sanitizeContext()` bu alanları filtreler. Ek olarak Node.js consumer'da defense-in-depth filter:

```typescript
// src/lib/sanitizer.ts
const SENSITIVE_KEYS = ['password', 'token', 'authorization', 'cookie', 'secret'];

export function sanitize(obj: any): any {
  if (typeof obj !== 'object' || obj === null) return obj;
  const result: any = Array.isArray(obj) ? [] : {};
  for (const [key, value] of Object.entries(obj)) {
    if (SENSITIVE_KEYS.some((k) => key.toLowerCase().includes(k))) {
      result[key] = '[REDACTED]';
    } else {
      result[key] = sanitize(value);
    }
  }
  return result;
}
```

### 13.3 Disaster Recovery

- ✅ Elasticsearch snapshot policy (daily, S3 veya benzer)
- ✅ RabbitMQ definitions backed up (declarative bootstrap zaten var)
- ✅ Audit fallback log files versioned in object storage

### 13.4 Scaling

- Node.js consumer **stateless**, replica eklemek yeter
- ES'te shard sayısı initial 1 (Test Projesi için yeterli)
- RabbitMQ cluster ileride gerekirse

---

## ✅ LOGGING SERVICE CHECKLIST

Implementation tamam sayılır eğer:

**Backend (Laravel):**
- [ ] `RabbitMqPublisher` (sync) yazıldı, publisher confirm mode
- [ ] `QueuedLogPublisher` (async via Laravel Queue) yazıldı
- [ ] `AuditLogger` implement, `AuditFallbackWriter` ile fallback'i var
- [ ] `RabbitMqLogChannel` Monolog handler yazıldı, `config/logging.php`'de registered
- [ ] `AccessLogMiddleware` yazıldı, global middleware
- [ ] Sensitive data sanitizer tüm publisher'larda çalışıyor
- [ ] Service provider registered
- [ ] `php artisan logging:replay-audit-fallback` komutu yazıldı

**RabbitMQ:**
- [ ] `definitions.json` doğru, exchange/queue/binding'ler tanımlı
- [ ] DLX/DLQ kuruldu
- [ ] Queue length limits set
- [ ] Local docker-compose'da çalışıyor

**Node.js Consumer:**
- [ ] TypeScript proje skeleton
- [ ] 3 consumer (audit, app, access) çalışıyor
- [ ] Bulk indexing
- [ ] Auto-reconnect logic
- [ ] Graceful shutdown
- [ ] Zod schema validation
- [ ] Sensitive data filter (defense-in-depth)
- [ ] Health check endpoint (HTTP /health)

**Elasticsearch:**
- [ ] Index templates installed
- [ ] ILM policies installed
- [ ] Aliases (audit-current, app-current, access-current) çalışıyor
- [ ] Kibana data views configured

**End-to-End Test:**
- [ ] Laravel'den audit log → ES'te 1 saniye içinde görünüyor
- [ ] Laravel'den info log → ES'te ~5 saniye içinde görünüyor
- [ ] HTTP request → access log ES'te görünüyor
- [ ] RabbitMQ down olunca → audit fallback file yazılıyor
- [ ] ES down olunca → consumer retry ediyor, queue dolmuyor (Redis aracılığıyla)

---

**Last Updated:** 2026-05-04
**Version:** 1.0
**Inspired By:** StkPro logging architecture
