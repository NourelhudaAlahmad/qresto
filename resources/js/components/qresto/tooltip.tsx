import * as TooltipPrimitive from '@radix-ui/react-tooltip';
import type { ReactNode } from 'react';

type TooltipProps = {
    content: ReactNode;
    children: ReactNode;
};

export function Tooltip({ content, children }: TooltipProps) {
    return (
        <TooltipPrimitive.Provider>
            <TooltipPrimitive.Root>
                <TooltipPrimitive.Trigger asChild>
                    {children}
                </TooltipPrimitive.Trigger>

                <TooltipPrimitive.Portal>
                    <TooltipPrimitive.Content
                        sideOffset={8}
                        className="text-micro z-50 rounded-[8px] bg-[var(--ink-900)] px-2.5 py-1.5 text-[var(--ink-25)] shadow-[var(--shadow-rest)] outline-none"
                    >
                        {content}

                        <TooltipPrimitive.Arrow className="fill-[var(--ink-900)]" />
                    </TooltipPrimitive.Content>
                </TooltipPrimitive.Portal>
            </TooltipPrimitive.Root>
        </TooltipPrimitive.Provider>
    );
}
