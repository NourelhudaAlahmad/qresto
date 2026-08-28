import * as RadioGroupPrimitive from '@radix-ui/react-radio-group';
import { CircleIcon } from 'lucide-react';
import * as React from 'react';

import { cn } from '@/lib/utils';

function RadioGroup({
    className,
    ...props
}: React.ComponentProps<typeof RadioGroupPrimitive.Root>) {
    return (
        <RadioGroupPrimitive.Root
            data-slot="qresto-radio-group"
            className={cn('grid gap-3', className)}
            {...props}
        />
    );
}

function Radio({
    className,
    ...props
}: React.ComponentProps<typeof RadioGroupPrimitive.Item>) {
    return (
        <span className="inline-flex min-h-[44px] min-w-[44px] items-center justify-center">
            <RadioGroupPrimitive.Item
                data-slot="qresto-radio"
                className={cn(
                    'border-border-default bg-surface-card peer size-5 shrink-0 rounded-full border',
                    'duration-fast ease-standard transition-[background-color,border-color,box-shadow,color]',
                    'outline-none',
                    'focus-visible:border-border-brand focus-visible:ring-[var(--clay-500)]/30 focus-visible:ring-[3px]',
                    'data-[state=checked]:border-action-primary data-[state=checked]:text-action-primary',
                    'disabled:cursor-not-allowed disabled:opacity-60',
                    className,
                )}
                {...props}
            >
                <RadioGroupPrimitive.Indicator
                    data-slot="qresto-radio-indicator"
                    className="flex items-center justify-center"
                >
                    <CircleIcon className="size-2.5 fill-current" />
                </RadioGroupPrimitive.Indicator>
            </RadioGroupPrimitive.Item>
        </span>
    );
}

export { Radio, RadioGroup };
