import type { HTMLAttributes, ReactNode } from 'react';

type NumericProps = HTMLAttributes<HTMLSpanElement> & {
    children: ReactNode;
};

export function Numeric({ children, className = '', ...props }: NumericProps) {
    return (
        <span
            {...props}
            className={`qr-numeric ${className}`.trim()}
            dir="ltr"
            data-numeric
        >
            {children}
        </span>
    );
}
