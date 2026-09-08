import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

/** Compone clases de Tailwind resolviendo los conflictos por especificidad. */
export function cn(...inputs: ClassValue[]): string {
    return twMerge(clsx(inputs));
}
