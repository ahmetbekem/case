# Frontend (Next.js 16 + Tailwind v4)

Feature-Sliced Design (FSD) + App Router + BFF Proxy Layer.

**Bootstrap:** ✅ TASK 0.14 tamam — Next.js 16, React 19, Tailwind v4, TypeScript 5.9. shadcn/ui için gerekli deps (clsx, tailwind-merge, class-variance-authority, lucide-react) yüklü; bileşenler TASK 0.16'da eklenecek.

> Not: TASK plan'ı "Next.js 15" diyor, `pnpm create next-app@latest` Next 16 verdi (16 GA olmuş). Backwards-compatible — App Router + RSC aynı API.

## Stack

- **Next.js 16** (App Router, RSC, Standalone output)
- **React 19** (stable Server Components + use())
- **Tailwind CSS v4** (yeni `@theme` directive, no `tailwind.config`)
- **TypeScript 5.9** (`strict` enabled)
- **State:** Zustand 5
- **Data:** TanStack React Query 5
- **Forms:** React Hook Form + Zod + @hookform/resolvers
- **Icons:** lucide-react

## FSD Layer Hiyerarşisi

```
src/
├── app/         # Next.js routing + BFF proxy (api/[...path], api/auth/*) — TASK 0.15
├── pages-ui/    # Page composition — TASK 0.16
├── widgets/     # Composite UI blocks (Header, ProductGrid) — TASK 0.16
├── features/    # User actions (cart-add-item, auth-login) — TASK 0.16
├── entities/    # Business entities (product, cart, order, user) — TASK 0.16
└── shared/      # UI kit, hooks, utils, fetch wrapper
    ├── api/         # server-fetch.ts (proxy-secret injection), types.ts (ApiResponse<T>)
    ├── config/      # env.ts (serverEnv vs publicEnv split)
    ├── hooks/
    ├── lib/         # cn.ts (Tailwind merge), format.ts (money/date)
    ├── types/
    └── ui/          # shadcn primitives — populated when first feature needs them
```

**Dependency:** Üst → alt yönde, asla tersi. Aynı katmanda yatay import YASAK. (TASK 0.16'da `eslint-plugin-boundaries` ile zorlanır.)

## Kritik Güvenlik Kuralları

> **Browser ASLA Laravel API'ye doğrudan istek atmaz.** Tüm istekler Next.js `/api/*` üzerinden geçer (TASK 0.15 — Proxy Layer).
>
> `NEXT_PUBLIC_*` prefix'iyle backend URL veya secret expose etmek YASAK. ESLint banned-imports kuralı bunu lint-time'da yakalar (`eslint.config.mjs`).
>
> `@/shared/api/server-fetch` `import 'server-only'` ile başlar — bir client component yanlışlıkla import etse build hard-fail eder.

## Komutlar

```bash
pnpm dev                                       # Next dev server (port 3000)
pnpm build                                     # Production build → .next/standalone
pnpm start                                     # Production server (after build)
pnpm lint                                      # ESLint (Next + TS + custom rules)
bash ../scripts/check-backend-leak.sh          # CI check — backend URL bundle'a sızmadı mı (TASK 0.15)
```

## Detay

- `../docs/PROJECT_STRUCTURE.md` §8-11 — FSD detayları, klasör organizasyonu
- `../docs/SECURITY.md` §3 — backend URL exposure, server-only fetch
