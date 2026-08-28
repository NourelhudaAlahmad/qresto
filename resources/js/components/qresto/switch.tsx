import * as SwitchPrimitive from '@radix-ui/react-switch';
import * as React from 'react';

import { cn } from '@/lib/utils';

function Switch({
    className,
    ...props
}: React.ComponentProps<typeof SwitchPrimitive.Root>) {
    return (
        <span className="inline-flex min-h-[44px] min-w-[44px] items-center justify-center">
            <SwitchPrimitive.Root
                data-slot="qresto-switch"
                className={cn(
                    'rounded-pill peer inline-flex h-6 w-11 shrink-0 cursor-pointer items-center',
                    'border-border-default bg-surface-sunken border',
                    'duration-fast ease-standard transition-[background-color,border-color,box-shadow]',
                    'outline-none',
                    'focus-visible:border-border-brand focus-visible:ring-[var(--clay-500)]/30 focus-visible:ring-[3px]',
                    'data-[state=checked]:border-action-primary data-[state=checked]:bg-action-primary',
                    'disabled:cursor-not-allowed disabled:opacity-60',
                    className,
                )}
                {...props}
            >
                <SwitchPrimitive.Thumb
                    data-slot="qresto-switch-thumb"
                    className={cn(
                        'shadow-rest pointer-events-none block size-5 rounded-full bg-white',
                        'duration-fast ease-standard transition-transform',
                        'data-[state=checked]:translate-x-5',
                        'data-[state=unchecked]:translate-x-0.5',
                    )}
                />
            </SwitchPrimitive.Root>
        </span>
    );
}

export { Switch };
