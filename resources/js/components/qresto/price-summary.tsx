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
    totalLabel?: string;
    subtotalLabel?: string;
    /** Rows after the total, e.g. Paid and Due when settling at the table. */
    afterTotal?: {
        label: string;
        value: string;
        tone?: 'positive' | 'warning';
    }[];
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
    totalLabel = 'Total',
    subtotalLabel = 'Subtotal',
    afterTotal = [],
    className = '',
}: PriceSummaryProps) {
    return (
        <div className={`flex flex-col gap-2 ${className}`}>
            <div className="text-body flex items-baseline">
                <span className="flex-1 text-[var(--text-secondary)]">
                    {subtotalLabel}
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
                    {totalLabel}
                </span>

                <Numeric className="text-title-2 font-semibold">
                    {total}
                </Numeric>
            </div>

            {afterTotal.map((row) => (
                <div key={row.label} className="text-body flex items-baseline">
                    <span
                        className={`flex-1 ${
                            row.tone === 'positive'
                                ? 'text-[var(--herb-700)]'
                                : row.tone === 'warning'
                                  ? 'font-semibold text-[var(--saffron-700)]'
                                  : 'text-[var(--text-secondary)]'
                        }`}
                    >
                        {row.label}
                    </span>

                    <Numeric
                        className={
                            row.tone === 'warning'
                                ? 'font-semibold text-[var(--saffron-700)]'
                                : ''
                        }
                    >
                        {row.value}
                    </Numeric>
                </div>
            ))}
        </div>
    );
}
