import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { extendTailwindMerge } from 'tailwind-merge';

/**
 * tailwind-merge has to know the design system's type scale. Without this
 * it reads `text-caption` as a text colour and silently drops it when a
 * colour class follows, e.g. cn('text-caption', 'text-[var(--berry-500)]').
 */
const twMerge = extendTailwindMerge({
    extend: {
        theme: {
            text: [
                'display-1',
                'display-2',
                'display-3',
                'title-1',
                'title-2',
                'title-3',
                'body',
                'body-lg',
                'label',
                'caption',
                'micro',
            ],
        },
    },
});

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}
