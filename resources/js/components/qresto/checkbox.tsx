import * as CheckboxPrimitive from '@radix-ui/react-checkbox';
import { CheckIcon } from 'lucide-react';
import * as React from 'react';

import { cn } from '@/lib/utils';

function Checkbox({
    className,
    ...props
}: React.ComponentProps<typeof CheckboxPrimitive.Root>) {
    return (
        <span className="inline-flex min-h-[44px] min-w-[44px] items-center justify-center">
            <CheckboxPrimitive.Root
                data-slot="qresto-checkbox"
                className={cn(
                    'rounded-xs border-border-default bg-surface-card peer size-5 shrink-0 border',
                    'duration-fast ease-standard transition-[background-color,border-color,box-shadow,color]',
                    'outline-none',
                    'focus-visible:border-border-brand focus-visible:ring-[var(--clay-500)]/30 focus-visible:ring-[3px]',
                    'data-[state=checked]:border-action-primary data-[state=checked]:bg-action-primary data-[state=checked]:text-text-on-brand',
                    'disabled:cursor-not-allowed disabled:opacity-60',
                    className,
                )}
                {...props}
            >
                <CheckboxPrimitive.Indicator
                    data-slot="qresto-checkbox-indicator"
                    className="flex items-center justify-center text-current"
                >
                    <CheckIcon className="size-3.5" />
                </CheckboxPrimitive.Indicator>
            </CheckboxPrimitive.Root>
        </span>
    );
}

export { Checkbox };
