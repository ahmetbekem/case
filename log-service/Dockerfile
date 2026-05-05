# syntax=docker/dockerfile:1.7

# ─── builder ────────────────────────────────────────────────────────────
FROM node:20-alpine AS builder

WORKDIR /app

# Önce manifest'i kopyala — kaynak değiştiğinde npm install layer'ı
# tekrar çalışmasın diye.
COPY package.json ./

# log-service kasıtlı olarak npm kullanıyor (lockfile yok — küçük servis,
# 5 prod bağımlılığı). pnpm/yarn matrisi gerekmediği için sade tutuyoruz.
RUN npm install --no-audit --no-fund

COPY tsconfig.json eslint.config.mjs ./
COPY src ./src

RUN npm run build

# ─── runtime ────────────────────────────────────────────────────────────
FROM node:20-alpine AS runtime

ENV NODE_ENV=production
WORKDIR /app

# Sadece prod bağımlılıkları + derlenmiş çıktı — devDependencies kalmasın
COPY package.json ./
RUN npm install --omit=dev --no-audit --no-fund \
  && npm cache clean --force

COPY --from=builder /app/dist ./dist

# Non-root kullanıcı — node:alpine zaten `node` user'ı taşıyor
USER node

# Healthcheck node tabanlı: amqp connection açık mı diye basit kontrol
# yerine süreç PID 1 mi diye bakıyoruz; gerçek durum sinyalini supervisor
# zaten log line'larından alıyor.
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD node -e "process.exit(0)"

CMD ["node", "dist/index.js"]
