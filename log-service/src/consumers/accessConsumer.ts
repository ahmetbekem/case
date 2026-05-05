import { startConsumer } from './baseConsumer.js';
import { bindingFor } from '../types.js';

export async function startAccessConsumer(): Promise<void> {
  await startConsumer({ binding: bindingFor('access') });
}
