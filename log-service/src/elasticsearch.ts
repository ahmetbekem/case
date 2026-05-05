import { Client, type estypes } from '@elastic/elasticsearch';

import { loadEnv } from './env.js';
import { logger } from './logger.js';

const env = loadEnv();

let client: Client | null = null;

export function esClient(): Client {
  if (client !== null) return client;

  client = new Client({
    node: env.ELASTICSEARCH_URL,
    ...(env.ELASTICSEARCH_USERNAME !== undefined &&
    env.ELASTICSEARCH_PASSWORD !== undefined
      ? {
          auth: {
            username: env.ELASTICSEARCH_USERNAME,
            password: env.ELASTICSEARCH_PASSWORD,
          },
        }
      : {}),
    requestTimeout: 10_000,
    sniffOnStart: false,
  });

  return client;
}

/**
 * Günlük rolling index ismi: logs-audit-2026.05.05 gibi.
 * Mesaj timestamp'i kendi günlük bucket'ına yazılır — tarih timestamp
 * yoksa (defansif) bugünün UTC tarihi kullanılır.
 */
export function indexNameFor(prefix: string, timestampIso?: string): string {
  const date =
    timestampIso !== undefined ? new Date(timestampIso) : new Date();
  const safe = Number.isNaN(date.getTime()) ? new Date() : date;
  const yyyy = safe.getUTCFullYear();
  const mm = String(safe.getUTCMonth() + 1).padStart(2, '0');
  const dd = String(safe.getUTCDate()).padStart(2, '0');
  return `${prefix}-${yyyy}.${mm}.${dd}`;
}

export async function indexDocument(
  prefix: string,
  document: Record<string, unknown>,
  timestampIso?: string,
): Promise<void> {
  const index = indexNameFor(prefix, timestampIso);
  const response: estypes.IndexResponse = await esClient().index({
    index,
    document,
  });
  logger.debug(
    { index, _id: response._id, result: response.result },
    'indexed document',
  );
}
