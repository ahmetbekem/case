/**
 * Backend tarafının yayınladığı zarf şekli. Buradaki tipler
 * "hot path" doğrulama için kasıtlı olarak gevşek (Record<string, unknown>) —
 * minimum gerekli alanları zorunlu kılıp geri kalanı serbest bırakıyoruz ki
 * şema evrimleştiğinde consumer ölmesin.
 */
export interface LogEnvelope {
  id?: string;
  timestamp?: string;
  level?: string;
  module?: string;
  action?: string;
  correlationId?: string;
  userId?: string | null;
  message?: string;
  context?: Record<string, unknown>;
  [extra: string]: unknown;
}

export type LogCategory = 'audit' | 'app' | 'access';

export interface QueueBinding {
  category: LogCategory;
  /** Queue ismi — durable */
  queueName: string;
  /** Topic exchange routing pattern, örn: log.audit.# */
  routingPattern: string;
  /** ES index prefix; tarih eki çalışma anında eklenir (logs-audit-2026.05.05) */
  indexPrefix: string;
}

export function bindingFor(category: LogCategory): QueueBinding {
  const found = BINDINGS.find((b) => b.category === category);
  if (found === undefined) {
    throw new Error(`binding missing for category: ${category}`);
  }
  return found;
}

export const BINDINGS: ReadonlyArray<QueueBinding> = [
  {
    category: 'audit',
    queueName: 'logs.audit.queue',
    routingPattern: 'log.audit.#',
    indexPrefix: 'logs-audit',
  },
  {
    category: 'app',
    queueName: 'logs.app.queue',
    routingPattern: 'log.app.#',
    indexPrefix: 'logs-app',
  },
  {
    category: 'access',
    queueName: 'logs.access.queue',
    routingPattern: 'log.access.#',
    indexPrefix: 'logs-access',
  },
];
