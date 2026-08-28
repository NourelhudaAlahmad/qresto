import * as React from 'react';

import { cn } from '@/lib/utils';

function Textarea({ className, ...props }: React.ComponentProps<'textarea'>) {
    return (
        <textarea
            data-slot="qresto-textarea"
            className={cn(
                'border-border-default bg-surface-card text-body text-text-primary min-h-[44px] w-full min-w-0 rounded-md border px-3 py-2 shadow-none',
                'placeholder:text-text-tertiary',
                'duration-fast ease-standard transition-[border-color,box-shadow,background-color]',
                'resize-y',
                'outline-none',
                'focus-visible:border-border-brand focus-visible:ring-[var(--clay-500)]/30 focus-visible:ring-[3px]',
                'disabled:bg-action-disabled-bg disabled:text-action-disabled-text disabled:cursor-not-allowed',
                'disabled:opacity-60',
                className,
            )}
            {...props}
        />
    );
}

export { Textarea };
