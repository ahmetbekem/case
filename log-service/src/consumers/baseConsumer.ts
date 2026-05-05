import type { Channel, ConsumeMessage } from 'amqplib';

import { indexDocument } from '../elasticsearch.js';
import { logger } from '../logger.js';
import { consume } from '../rabbitmq.js';
import type { LogEnvelope, QueueBinding } from '../types.js';

/**
 * 3 kategori için ortak akış:
 *   1. JSON parse (başarısız → DLX, ack ettirme)
 *   2. Routing key + meta zenginleştir
 *   3. ES index (başarısız → nack noRequeue → DLX)
 *   4. Başarılıysa ack
 *
 * Per-kategori özel transform isteyen consumer kendi `transform`unu
 * geçer; default identity dönüşümü zarfı olduğu gibi indeksler.
 */
export interface ConsumerOptions {
  binding: QueueBinding;
  transform?: (
    envelope: LogEnvelope,
    msg: ConsumeMessage,
  ) => Record<string, unknown>;
}

export async function startConsumer({
  binding,
  transform,
}: ConsumerOptions): Promise<void> {
  await consume(binding, async (msg: ConsumeMessage, ch: Channel) => {
    const routingKey = msg.fields.routingKey;
    let envelope: LogEnvelope;

    try {
      envelope = JSON.parse(msg.content.toString('utf8')) as LogEnvelope;
    } catch (err) {
      logger.warn(
        { err, routingKey, queue: binding.queueName },
        'invalid JSON — routing to DLX',
      );
      ch.nack(msg, false, false);
      return;
    }

    const document =
      transform !== undefined
        ? transform(envelope, msg)
        : {
            ...envelope,
            '@timestamp': envelope.timestamp ?? new Date().toISOString(),
            routingKey,
            category: binding.category,
          };

    try {
      await indexDocument(
        binding.indexPrefix,
        document,
        envelope.timestamp,
      );
      ch.ack(msg);
    } catch (err) {
      logger.error(
        { err, routingKey, id: envelope.id },
        'elasticsearch index failed — routing to DLX',
      );
      ch.nack(msg, false, false);
    }
  });
}
