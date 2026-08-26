import * as React from 'react';

import { cn } from '@/lib/utils';

function Textarea({
    className,
    ...props
}: React.ComponentProps<'textarea'>) {
    return (
        <textarea
            data-slot="qresto-textarea"
            className={cn(
                'min-h-[44px] w-full min-w-0 rounded-md border border-border-default bg-surface-card px-3 py-2 text-body text-text-primary shadow-none',
                'placeholder:text-text-tertiary',
                'transition-[border-color,box-shadow,background-color] duration-fast ease-standard',
                'resize-y',
                'outline-none',
                'focus-visible:border-border-brand focus-visible:ring-[3px] focus-visible:ring-[var(--clay-500)]/30',
                'disabled:cursor-not-allowed disabled:bg-action-disabled-bg disabled:text-action-disabled-text',
                'disabled:opacity-60',
                className,
            )}
            {...props}
        />
    );
}

export { Textarea };