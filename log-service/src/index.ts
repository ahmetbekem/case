import { startAccessConsumer } from './consumers/accessConsumer.js';
import { startAppConsumer } from './consumers/appConsumer.js';
import { startAuditConsumer } from './consumers/auditConsumer.js';
import { logger } from './logger.js';
import { connect, shutdown } from './rabbitmq.js';

async function main(): Promise<void> {
  logger.info('log-service starting');

  // Tek connection + topology'yi açıkça boot'ta kur — consumer'lar
  // sonradan aynı channel'a bağlanır.
  await connect();

  await Promise.all([
    startAuditConsumer(),
    startAppConsumer(),
    startAccessConsumer(),
  ]);

  logger.info('log-service ready');
}

let shuttingDown = false;
async function gracefulShutdown(signal: string): Promise<void> {
  if (shuttingDown) return;
  shuttingDown = true;
  logger.info({ signal }, 'shutdown requested');
  try {
    await shutdown();
  } finally {
    process.exit(0);
  }
}

process.on('SIGTERM', () => void gracefulShutdown('SIGTERM'));
process.on('SIGINT', () => void gracefulShutdown('SIGINT'));
process.on('uncaughtException', (err) => {
  logger.fatal({ err }, 'uncaughtException — exiting');
  process.exit(1);
});
process.on('unhandledRejection', (reason) => {
  logger.fatal({ reason }, 'unhandledRejection — exiting');
  process.exit(1);
});

main().catch((err: unknown) => {
  logger.fatal({ err }, 'log-service failed to start');
  process.exit(1);
});
