import { startConsumer } from './baseConsumer.js';
import { bindingFor } from '../types.js';

/**
 * Audit zarfları sync publish + replay yoluyla geliyor; replayed mesajları
 * routing key sonunda `.replayed` suffix taşır. UI sorgularken bu suffix
 * orijinal action'dan ayırt edilebilsin diye index'e kopyalıyoruz.
 */
export async function startAuditConsumer(): Promise<void> {
  await startConsumer({
    binding: bindingFor('audit'),
    transform: (envelope, msg) => {
      const routingKey = msg.fields.routingKey;
      return {
        ...envelope,
        '@timestamp': envelope.timestamp ?? new Date().toISOString(),
        routingKey,
        category: 'audit',
        isReplay: routingKey.endsWith('.replayed'),
      };
    },
  });
}
