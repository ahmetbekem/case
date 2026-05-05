import amqp, {
  type ChannelModel,
  type Channel,
  type ConsumeMessage,
} from 'amqplib';

import { loadEnv } from './env.js';
import { logger } from './logger.js';
import { BINDINGS, type QueueBinding } from './types.js';

const env = loadEnv();

let connection: ChannelModel | null = null;
let channel: Channel | null = null;

/**
 * Tek bir TCP connection + tek bir channel kullanıyoruz — 3 consumer da
 * aynı channel üstünde dinler. Yüksek throughput'ta channel başına
 * consumer'a bölmek mantıklı olur ama skeleton için yeterli.
 */
export async function connect(): Promise<Channel> {
  if (channel !== null) return channel;

  const url = `amqp://${env.RABBITMQ_USER}:${env.RABBITMQ_PASSWORD}@${env.RABBITMQ_HOST}:${env.RABBITMQ_PORT}${env.RABBITMQ_VHOST === '/' ? '/' : `/${encodeURIComponent(env.RABBITMQ_VHOST)}`}`;

  logger.info(
    { host: env.RABBITMQ_HOST, port: env.RABBITMQ_PORT, vhost: env.RABBITMQ_VHOST },
    'connecting to RabbitMQ',
  );

  connection = await amqp.connect(url, { heartbeat: 30 });

  connection.on('error', (err) => {
    logger.error({ err }, 'rabbitmq connection error');
  });
  connection.on('close', () => {
    // Process-level supervisor (docker restart policy) yeniden başlatır.
    logger.error('rabbitmq connection closed — exiting for supervisor restart');
    process.exit(1);
  });

  channel = await connection.createChannel();
  await channel.prefetch(env.CONSUMER_PREFETCH);

  await assertTopology(channel);
  return channel;
}

/**
 * TASK 0.18 declarative definitions.json mount'u tamamlanana kadar
 * topology'yi defansif olarak burada da tanımlıyoruz. Tüm declare'ler
 * idempotent — broker tarafında tip uyuşmazlığı yoksa noop.
 */
async function assertTopology(ch: Channel): Promise<void> {
  await ch.assertExchange(env.LOGS_EXCHANGE, 'topic', { durable: true });
  await ch.assertExchange(env.LOGS_DLX_EXCHANGE, 'topic', { durable: true });

  // Tek catch-all DLQ — kötü mesajlar burada birikir, manuel inceleme için
  await ch.assertQueue(env.LOGS_DLQ_NAME, { durable: true });
  await ch.bindQueue(env.LOGS_DLQ_NAME, env.LOGS_DLX_EXCHANGE, '#');

  for (const binding of BINDINGS) {
    await ch.assertQueue(binding.queueName, {
      durable: true,
      arguments: {
        'x-dead-letter-exchange': env.LOGS_DLX_EXCHANGE,
      },
    });
    await ch.bindQueue(binding.queueName, env.LOGS_EXCHANGE, binding.routingPattern);
  }
  logger.info({ bindings: BINDINGS.length }, 'topology asserted');
}

export async function consume(
  binding: QueueBinding,
  handler: (msg: ConsumeMessage, ch: Channel) => Promise<void>,
): Promise<void> {
  const ch = await connect();
  await ch.consume(
    binding.queueName,
    (msg) => {
      if (msg === null) return;
      void handler(msg, ch).catch((err) => {
        logger.error(
          { err, queue: binding.queueName, routingKey: msg.fields.routingKey },
          'unhandled consumer error — nacking to DLX',
        );
        ch.nack(msg, false, false);
      });
    },
    { noAck: false },
  );
  logger.info({ queue: binding.queueName }, 'consumer started');
}

export async function shutdown(): Promise<void> {
  try {
    await channel?.close();
  } catch (err) {
    logger.warn({ err }, 'error closing channel');
  }
  try {
    await connection?.close();
  } catch (err) {
    logger.warn({ err }, 'error closing connection');
  }
  channel = null;
  connection = null;
}
