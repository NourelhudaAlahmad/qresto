import { Slot } from '@radix-ui/react-slot';

import { cva } from 'class-variance-authority';

import type { VariantProps } from 'class-variance-authority';

import * as React from 'react';

import { cn } from '@/lib/utils';

const buttonVariants = cva(
    [
        'inline-flex items-center justify-center gap-2 whitespace-nowrap',
        'min-w-[44px]',

        'relative isolate',
        'before:absolute before:-inset-1',
        'before:min-h-[44px] before:min-w-[44px]',
        'before:pointer-events-auto',
        'font-ui font-medium',
        'transition-[background-color,color,border-color,box-shadow,transform,opacity]',
        'duration-fast',
        'ease-standard',
        'outline-none',
        'focus-visible:ring-[3px]',
        'focus-visible:ring-offset-0',
        'disabled:pointer-events-none',
        'disabled:cursor-not-allowed',
        '[&_svg]:pointer-events-none',
        '[&_svg]:shrink-0',
    ],
    {
        variants: {
            variant: {
                primary: [
                    'rounded-pill',
                    'bg-action-primary',
                    'text-text-on-brand',
                    'hover:bg-action-primary-hover',
                    'active:bg-action-primary-active',
                    'focus-visible:ring-action-primary/30',
                ],

                secondary: [
                    'rounded-pill',
                    'bg-action-secondary',
                    'text-text-on-brand',
                    'hover:bg-action-secondary-hover',
                    'focus-visible:ring-action-secondary/30',
                ],

                outline: [
                    'rounded-pill',
                    'border',
                    'border-border-default',
                    'bg-surface-card',
                    'text-text-primary',
                    'hover:bg-action-ghost-hover',
                    'focus-visible:ring-border-focus-color/30',
                ],

                ghost: [
                    'rounded-pill',
                    'bg-transparent',
                    'text-text-primary',
                    'hover:bg-action-ghost-hover',
                    'focus-visible:ring-border-focus-color/30',
                ],

                destructive: [
                    'rounded-pill',
                    'bg-danger-solid',
                    'text-text-on-brand',
                    'hover:opacity-90',
                    'focus-visible:ring-danger/30',
                ],

                disabled: [
                    'rounded-pill',
                    'bg-action-disabled-bg',
                    'text-action-disabled-text',
                    'opacity-60',
                ],
            },

            size: {
                sm: 'text-label h-[34px] px-4',
                md: 'text-label h-[44px] px-5',
                lg: 'text-body-lg h-[54px] px-6',
            },

            fullWidth: {
                true: 'w-full',
                false: '',
            },
        },

        compoundVariants: [
            {
                variant: 'disabled',
                className: 'hover:bg-action-disabled-bg active:scale-100',
            },
        ],

        defaultVariants: {
            variant: 'primary',
            size: 'md',
            fullWidth: false,
        },
    },
);

type ButtonProps = React.ComponentProps<'button'> &
    VariantProps<typeof buttonVariants> & {
        asChild?: boolean;
        loading?: boolean;
    };

function Button({
    className,
    variant,
    size,
    fullWidth,
    loading = false,
    asChild = false,
    disabled,
    children,
    ...props
}: ButtonProps) {
    const Comp = asChild ? Slot : 'button';

    const isDisabled = disabled || loading;

    return (
        <Comp
            data-slot="qresto-button"
            data-loading={loading || undefined}
            aria-busy={loading || undefined}
            disabled={!asChild ? isDisabled : undefined}
            className={cn(
                buttonVariants({
                    variant,
                    size,
                    fullWidth,
                    className,
                }),
                !loading && !disabled && 'active:scale-[var(--press-scale)]',
            )}
            {...props}
        >
            {loading ? (
                <>
                    <span
                        aria-hidden="true"
                        className="size-4 animate-spin rounded-full border-2 border-current border-t-transparent"
                    />
                    <span>Loading...</span>
                </>
            ) : (
                children
            )}
        </Comp>
    );
}

export { Button, buttonVariants };
