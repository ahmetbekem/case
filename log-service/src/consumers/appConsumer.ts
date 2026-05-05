import { startConsumer } from './baseConsumer.js';
import { bindingFor } from '../types.js';

export async function startAppConsumer(): Promise<void> {
  await startConsumer({ binding: bindingFor('app') });
}
