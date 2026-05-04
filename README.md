# Dehasoft Test Projesi

10 günlük teknik test — basit e-ticaret (auth + ürün + sepet + sipariş + multi-currency).

**Stack:** Laravel 11 + PostgreSQL 15 + Redis 7 + RabbitMQ 3.13 + Elasticsearch 8.13 + Next.js 15 + Node.js 20

**Mimari:** Onion + CQRS + DDD (Lite) + FSD (frontend) + Decoupled Logging Pipeline

## Monorepo Yapısı

```
dehasoft-test/
├── backend/         # Laravel 11 API (PHP 8.2+)
├── frontend/        # Next.js 15 (TypeScript, App Router, FSD)
├── log-service/     # Node.js 20 RabbitMQ → Elasticsearch consumer
├── infrastructure/  # RabbitMQ + Elasticsearch declarative configs
├── scripts/         # Helper scripts (CI checks, secret leak detection, etc.)
├── docs/            # Architecture documentation
└── .github/         # CI workflows
```

## Doküman Haritası

| Dosya | İçerik |
|-------|--------|
| `CLAUDE.md` | Glob patterns, en çok yapılan hatalar, hızlı kod kalıpları |
| `DECISIONS.md` | 15 mimari kararın ADR formatı |
| `docs/PROJECT_STRUCTURE.md` | Klasör yapısı, modüller, FSD, dependency rules |
| `docs/DATABASE_SCHEMA.md` | PostgreSQL şemaları, tablolar, indexler |
| `docs/CODING_STANDARDS.md` | Kod template'leri (VO, Entity, Handler, Repo, Controller) |
| `docs/LOGGING_SERVICE.md` | RabbitMQ + Node.js + Elasticsearch pipeline |
| `docs/SECURITY.md` | 5 güvenlik tehdidi + mitigation + test checklist |

## Quick Start

> Servisler henüz bootstrap edilmedi. TASK 0.4 (backend), TASK 0.14 (frontend), TASK 0.17 (log-service) tamamlandığında bu bölüm güncellenecek.

```bash
docker-compose up -d  # TASK 0.3'te eklenecek
```
