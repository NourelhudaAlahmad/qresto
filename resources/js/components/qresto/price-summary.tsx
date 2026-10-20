import { Numeric } from '@/components/numeric';

type PriceSummaryProps = {
    subtotal: string;
    service?: string;
    serviceLabel?: string;
    discount?: string;
    discountLabel?: string;
    tip?: string;
    tipLabel?: string;
    split?: string;
    splitLabel?: string;
    total: string;
    className?: string;
};

export function PriceSummary({
    subtotal,
    service,
    serviceLabel = 'Service',
    discount,
    discountLabel = 'Discount',
    tip,
    tipLabel = 'Tip',
    split,
    splitLabel = 'Split',
    total,
    className = '',
}: PriceSummaryProps) {
    return (
        <div className={`flex flex-col gap-2 ${className}`}>
            <div className="text-body flex items-baseline">
                <span className="flex-1 text-[var(--text-secondary)]">
                    Subtotal
                </span>

                <Numeric>{subtotal}</Numeric>
            </div>

            {service !== undefined && (
                <div className="text-body flex items-baseline">
                    <span className="flex-1 text-[var(--text-secondary)]">
                        {serviceLabel}
                    </span>

                    <Numeric>{service}</Numeric>
                </div>
            )}

            {discount !== undefined && (
                <div className="text-body flex items-baseline">
                    <span className="flex-1 text-[var(--text-secondary)]">
                        {discountLabel}
                    </span>

                    <Numeric>{discount}</Numeric>
                </div>
            )}

            {tip !== undefined && (
                <div className="text-body flex items-baseline">
                    <span className="flex-1 text-[var(--text-secondary)]">
                        {tipLabel}
                    </span>

                    <Numeric>{tip}</Numeric>
                </div>
            )}

            {split !== undefined && (
                <div className="text-body flex items-baseline">
                    <span className="flex-1 text-[var(--text-secondary)]">
                        {splitLabel}
                    </span>

                    <Numeric>{split}</Numeric>
                </div>
            )}

            <div className="my-1 h-px bg-[var(--border-subtle)]" />

            <div className="text-body flex items-baseline">
                <span className="flex-1 font-semibold text-[var(--text-primary)]">
                    Total
                </span>

                <Numeric className="text-title-2 font-semibold">
                    {total}
                </Numeric>
            </div>
        </div>
    );
}
