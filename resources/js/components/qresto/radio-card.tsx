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
                'border-border-default bg-surface-card rounded-md border px-4',
                'duration-fast ease-standard transition-[background-color,border-color,box-shadow]',
                'has-[[data-state=checked]]:border-border-brand',
                'has-[[data-state=checked]]:bg-surface-brand-soft',
                'has-[[data-state=checked]]:shadow-none',
                'hover:bg-action-ghost-hover',
                'focus-within:border-border-brand',
                'focus-within:ring-[var(--clay-500)]/30 focus-within:ring-[3px]',
                'has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60',
                className,
            )}
        >
            <Radio id={id} {...props} />

            <span className="min-w-0 flex-1">
                <span className="text-label text-text-primary block font-medium">
                    {title}
                </span>

                {description ? (
                    <span className="text-caption text-text-secondary mt-0.5 block">
                        {description}
                    </span>
                ) : null}
            </span>
        </label>
    );
}

export { RadioCard, RadioGroup };
