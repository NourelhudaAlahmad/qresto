import * as React from 'react';

import { cn } from '@/lib/utils';

function Input({
    className,
    type = 'text',
    ...props
}: React.ComponentProps<'input'>) {
    return (
        <input
            type={type}
            data-slot="qresto-input"
            className={cn(
                'border-border-default bg-surface-card text-body text-text-primary h-[44px] w-full min-w-0 rounded-md border px-3 py-2 shadow-none',
                'placeholder:text-text-tertiary',
                'duration-fast ease-standard transition-[border-color,box-shadow,background-color]',
                'outline-none',
                'focus-visible:ring-[var(--clay-500)]/30 focus-visible:border-[var(--border-brand)] focus-visible:ring-[3px]',
                'disabled:bg-action-disabled-bg disabled:text-action-disabled-text disabled:cursor-not-allowed',
                'disabled:opacity-60',
                'file:border-0 file:bg-transparent file:text-sm file:font-medium',
                className,
            )}
            {...props}
        />
    );
}

export { Input };
