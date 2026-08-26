import * as React from 'react';

import { Radio, RadioGroup } from '@/components/qresto/radio';
import { cn } from '@/lib/utils';

type RadioCardProps = React.ComponentProps<typeof Radio> & {
    title: string;
    description?: string;
};

function RadioCard({
    className,
    title,
    description,
    ...props
}: RadioCardProps) {
    const id = React.useId();

    return (
        <label
            htmlFor={id}
            className={cn(
                'flex min-h-[52px] w-full cursor-pointer items-center gap-3',
                'rounded-md border border-border-default bg-surface-card px-4',
                'transition-[background-color,border-color,box-shadow] duration-fast ease-standard',
                'has-[[data-state=checked]]:border-border-brand',
                'has-[[data-state=checked]]:bg-surface-brand-soft',
                'has-[[data-state=checked]]:shadow-none',
                'hover:bg-action-ghost-hover',
                'focus-within:border-border-brand',
                'focus-within:ring-[3px] focus-within:ring-[var(--clay-500)]/30',
                'has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60',
                className,
            )}
        >
            <Radio id={id} {...props} />

            <span className="min-w-0 flex-1">
                <span className="block text-label font-medium text-text-primary">
                    {title}
                </span>

                {description ? (
                    <span className="mt-0.5 block text-caption text-text-secondary">
                        {description}
                    </span>
                ) : null}
            </span>
        </label>
    );
}

export { RadioCard, RadioGroup };