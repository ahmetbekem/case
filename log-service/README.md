# Log Service (Node.js 20 + TypeScript)

RabbitMQ → Elasticsearch log shipping consumer. 3 paralel consumer (audit, application, access).

**Bootstrap:** TASK 0.17'de oluşturulacak.

## Mimari

```
RabbitMQ (logs.exchange, topic)
  ├── logs.audit       → AuditConsumer       (immediate, 1 doc/100ms)
  ├── logs.application → ApplicationConsumer (batch, 100 docs/5s)
  └── logs.access      → AccessConsumer      (batch, 500 docs/5s)
                              ↓ Bulk API
                       Elasticsearch
                              ├── audit-YYYY.MM   (1 yıl retention)
                              ├── app-YYYY.MM.DD  (30 gün retention)
                              └── access-YYYY.MM.DD (14 gün retention)
```

## Stack

- `amqplib` — RabbitMQ client (auto-reconnect with exponential backoff)
- `@elastic/elasticsearch` — ES bulk indexer
- `zod` — message envelope + payload validation
- `pino` — structured internal logging

## Komutlar

```bash
pnpm dev                                       # tsx watch mode
pnpm build && node dist/index.js               # Production
pnpm test                                       # Vitest
```

Detay: `../docs/LOGGING_SERVICE.md`
