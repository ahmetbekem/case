import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

/**
 * Combine conditional class names and de-dupe Tailwind atoms.
 * Standard shadcn-flavoured `cn` utility — every component composing
 * Tailwind classes goes through this so later overrides win.
 */
export function cn(...inputs: ClassValue[]): string {
  return twMerge(clsx(inputs));
}
