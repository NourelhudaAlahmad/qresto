type StatProps = {
    label: React.ReactNode;
    value: React.ReactNode;
    hint?: React.ReactNode;
    hintTone?: 'default' | 'positive' | 'warning';
    className?: string;
};

const hintStyles: Record<NonNullable<StatProps['hintTone']>, string> = {
    default: 'text-[var(--text-secondary)]',
    positive: 'text-[var(--herb-700)]',
    warning: 'text-[var(--saffron-700)]',
};

export function Stat({
    label,
    value,
    hint,
    hintTone = 'default',
    className = '',
}: StatProps) {
    return (
        <div
            className={`rounded-[var(--radius-lg)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-4 shadow-[var(--shadow-rest)] ${className}`}
        >
            <div className="text-caption text-[var(--text-tertiary)]">
                {label}
            </div>

            <div className="font-mono text-display-3 leading-[1.1]">
                {value}
            </div>

            {hint !== undefined && (
                <div
                    className={`flex items-center gap-1 text-caption ${hintStyles[hintTone]}`}
                >
                    {hint}
                </div>
            )}
        </div>
    );
}