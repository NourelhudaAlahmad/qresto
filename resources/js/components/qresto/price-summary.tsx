import { Numeric } from '@/components/numeric';

type PriceSummaryProps = {
    subtotal: string;
    service?: string;
    discount?: string;
    tip?: string;
    split?: string;
    total: string;
    className?: string;
};

export function PriceSummary({
    subtotal,
    service,
    discount,
    tip,
    split,
    total,
    className = '',
}: PriceSummaryProps) {
    return (
        <div className={`flex flex-col gap-2 ${className}`}>
            {' '}
            <div className="text-body flex items-baseline">
                {' '}
                <span className="flex-1 text-[var(--text-secondary)]">
                    Subtotal{' '}
                </span>
                <Numeric>{subtotal}</Numeric>
            </div>
            {service !== undefined && (
                <div className="text-body flex items-baseline">
                    <span className="flex-1 text-[var(--text-secondary)]">
                        Service
                    </span>

                    <Numeric>{service}</Numeric>
                </div>
            )}
            {discount !== undefined && (
                <div className="text-body flex items-baseline">
                    <span className="flex-1 text-[var(--text-secondary)]">
                        Discount
                    </span>

                    <Numeric>{discount}</Numeric>
                </div>
            )}
            {tip !== undefined && (
                <div className="text-body flex items-baseline">
                    <span className="flex-1 text-[var(--text-secondary)]">
                        Tip
                    </span>

                    <Numeric>{tip}</Numeric>
                </div>
            )}
            {split !== undefined && (
                <div className="text-body flex items-baseline">
                    <span className="flex-1 text-[var(--text-secondary)]">
                        Split
                    </span>

                    <Numeric>{split}</Numeric>
                </div>
            )}
            <div className="my-1 h-px bg-[var(--border-subtle)]" />
            <div className="text-body flex items-baseline">
                <span className="flex-1 text-[var(--text-secondary)]">
                    Total
                </span>

                <Numeric className="text-title-2 font-medium">{total}</Numeric>
            </div>
        </div>
    );
}
