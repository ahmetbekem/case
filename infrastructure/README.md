# Infrastructure

Declarative configs for RabbitMQ + Elasticsearch. Bootstrap'lar TASK 0.18'de doldurulacak.

## Yapı

```
infrastructure/
├── rabbitmq/
│   ├── definitions.json    # Exchange + queue + binding definitions
│   └── rabbitmq.conf       # Server config
└── elasticsearch/
    ├── templates/          # Index templates (audit, app, access)
    ├── ilm-policies/       # Index Lifecycle Management policies
    └── bootstrap.sh        # Initial setup script (templates + ILM apply)
```

## RabbitMQ Topology

```
Exchange: logs.exchange (topic, durable)
  ├── logs.audit       (routing: log.audit.#)   — DLX, 100K max, 7d TTL
  ├── logs.application (routing: log.app.#)     — DLX, 1M max, 24h TTL
  └── logs.access      (routing: log.access.#)  — DLX, 5M max, 12h TTL

Dead Letter Exchange: logs.dlx (fanout) → logs.dlq
```

## Elasticsearch Indices

| Index Pattern | Rollover | Retention | ILM Policy |
|---------------|----------|-----------|------------|
| `audit-YYYY.MM` | aylık | 365 gün | `audit-policy` |
| `app-YYYY.MM.DD` | günlük | 30 gün | `app-policy` |
| `access-YYYY.MM.DD` | günlük | 14 gün | `access-policy` |

Detay: `../docs/LOGGING_SERVICE.md` §5, §6, §12
