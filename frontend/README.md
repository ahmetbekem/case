# Frontend (Next.js 15)

Feature-Sliced Design (FSD) + App Router + BFF Proxy Layer.

**Bootstrap:** TASK 0.14'te `pnpm create next-app` ile kurulacak.

## FSD Layer Hiyerarşisi

```
src/
├── app/         # Next.js routing + BFF proxy (api/[...path], api/auth/*)
├── pages-ui/    # Page composition
├── widgets/     # Composite UI blocks (Header, ProductGrid)
├── features/    # User actions (cart-add-item, auth-login)
├── entities/    # Business entities (product, cart, order, user)
└── shared/      # UI kit, hooks, utils, fetch wrapper
```

**Dependency:** Üst → alt yönde, asla tersi. Aynı katmanda yatay import YASAK.

## Kritik Güvenlik Kuralı

> Browser ASLA Laravel API'ye doğrudan istek atmaz. Tüm istekler Next.js `/api/*` üzerinden geçer.
> `NEXT_PUBLIC_*` prefix'iyle backend URL veya secret expose etmek YASAK.

## Komutlar

```bash
pnpm dev
pnpm build
pnpm test
bash ../scripts/check-backend-leak.sh   # CI check — backend URL bundle'a sızmadı mı
```

Detay: `../docs/PROJECT_STRUCTURE.md` §8-11, `../docs/SECURITY.md` §3
