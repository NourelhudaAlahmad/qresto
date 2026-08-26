import * as React from 'react';

import { Button } from '@/components/qresto/button';
import { cn } from '@/lib/utils';

type QuantityStepperProps = {
    value?: number;
    min?: number;
    max?: number;
    size?: 'sm' | 'md';
    onChange?: (value: number) => void;
    disabled?: boolean;
    className?: string;
};

function QuantityStepper({
    value = 1,
    min = 1,
    max,
    size = 'md',
    onChange,
    disabled = false,
    className,
}: QuantityStepperProps) {
    const decrease = () => {
        if (disabled || value <= min) {
            return;
        }

        onChange?.(value - 1);
    };

    const increase = () => {
        if (disabled || (max !== undefined && value >= max)) {
            return;
        }

        onChange?.(value + 1);
    };

    const isMin = value <= min;
    const isMax = max !== undefined && value >= max;
    const isCompact = size === 'sm';

    const buttonSize = 'size-11 p-0';
    const visualSize = isCompact ? 'size-9' : 'size-11';

    return (
        <div
            data-slot="qresto-quantity-stepper"
            className={cn(
                'inline-flex items-center gap-1 rounded-pill',
                'bg-surface-card',
                className,
            )}
        >
            <Button
                type="button"
                variant="ghost"
                size="md"
                aria-label="Decrease quantity"
                disabled={disabled || isMin}
                onClick={decrease}
                className={cn(buttonSize, 'group')}
            >
                <span
                    className={cn(
                        'flex items-center justify-center rounded-pill',
                        visualSize,
                        'transition-colors duration-fast ease-standard',
                        'group-hover:bg-action-ghost-hover',
                    )}
                >
                    −
                </span>
            </Button>

            <span
                data-numeric
                aria-live="polite"
                className="flex min-w-[2.25rem] items-center justify-center text-label font-medium text-text-primary"
            >
                {value}
            </span>

            <Button
                type="button"
                variant="ghost"
                size="md"
                aria-label="Increase quantity"
                disabled={disabled || isMax}
                onClick={increase}
                className={cn(buttonSize, 'group')}
            >
                <span
                    className={cn(
                        'flex items-center justify-center rounded-pill',
                        visualSize,
                        'transition-colors duration-fast ease-standard',
                        'group-hover:bg-action-ghost-hover',
                    )}
                >
                    +
                </span>
            </Button>
        </div>
    );
}

export { QuantityStepper };