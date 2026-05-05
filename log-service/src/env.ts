import { z } from 'zod';

/**
 * Tüm runtime config tek bir yerden çekilir, eksik veya tipi yanlış env
 * varsa süreç boot anında patlar — geç hata yerine erken hata.
 */
const schema = z.object({
  NODE_ENV: z
    .enum(['development', 'production', 'test'])
    .default('development'),
  LOG_LEVEL: z
    .enum(['fatal', 'error', 'warn', 'info', 'debug', 'trace'])
    .default('info'),

  RABBITMQ_HOST: z.string().min(1).default('rabbitmq'),
  RABBITMQ_PORT: z.coerce.number().int().positive().default(5672),
  RABBITMQ_USER: z.string().min(1).default('admin'),
  RABBITMQ_PASSWORD: z.string().min(1).default('admin'),
  RABBITMQ_VHOST: z.string().min(1).default('/'),

  ELASTICSEARCH_URL: z
    .string()
    .url()
    .default('http://elasticsearch:9200'),
  ELASTICSEARCH_USERNAME: z.string().optional(),
  ELASTICSEARCH_PASSWORD: z.string().optional(),

  // Topic exchange — backend ile birebir aynı olmalı
  LOGS_EXCHANGE: z.string().min(1).default('logs.exchange'),
  // DLX — kötü mesajlar burada birikir (parse / index başarısızlığı)
  LOGS_DLX_EXCHANGE: z.string().min(1).default('logs.dlx'),
  LOGS_DLQ_NAME: z.string().min(1).default('logs.dlq'),

  // Consumer prefetch — düşük tutarsak ack latency'i daha çabuk geri besleriz
  CONSUMER_PREFETCH: z.coerce.number().int().positive().default(20),
});

export type Env = z.infer<typeof schema>;

let cached: Env | null = null;

export function loadEnv(): Env {
  if (cached !== null) return cached;
  const parsed = schema.safeParse(process.env);
  if (!parsed.success) {
    // Pino henüz yok — env okunamıyorsa stderr'e ham yaz, exit 1
    console.error(
      '[log-service] env validation failed:',
      JSON.stringify(parsed.error.flatten().fieldErrors, null, 2),
    );
    process.exit(1);
  }
  cached = parsed.data;
  return cached;
}
