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
            <div className="text-body flex items-baseline">
                <span className="flex-1 text-[var(--text-secondary)]">
                    Subtotal
                </span>

                <span className="font-mono">{subtotal}</span>
            </div>

            {service !== undefined && (
                <div className="text-body flex items-baseline">
                    <span className="flex-1 text-[var(--text-secondary)]">
                        Service
                    </span>

                    <span className="font-mono">{service}</span>
                </div>
            )}

            {discount !== undefined && (
                <div className="text-body flex items-baseline">
                    <span className="flex-1 text-[var(--text-secondary)]">
                        Discount
                    </span>

                    <span className="font-mono">{discount}</span>
                </div>
            )}

            {tip !== undefined && (
                <div className="text-body flex items-baseline">
                    <span className="flex-1 text-[var(--text-secondary)]">
                        Tip
                    </span>

                    <span className="font-mono">{tip}</span>
                </div>
            )}

            {split !== undefined && (
                <div className="text-body flex items-baseline">
                    <span className="flex-1 text-[var(--text-secondary)]">
                        Split
                    </span>

                    <span className="font-mono">{split}</span>
                </div>
            )}

            <div className="my-1 h-px bg-[var(--border-subtle)]" />

            <div className="text-body flex items-baseline">
                <span className="flex-1 text-[var(--text-secondary)]">
                    Total
                </span>

                <span className="text-title-2 font-mono font-medium">
                    {total}
                </span>
            </div>
        </div>
    );
}
