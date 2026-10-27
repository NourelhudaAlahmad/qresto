type StatProps = {
    label: React.ReactNode;
    value: React.ReactNode;
    hint?: React.ReactNode;
    hintTone?: 'default' | 'positive' | 'warning' | 'negative';
    /** inverse: ink card for the one figure that leads a row (admin MTD). */
    tone?: 'default' | 'inverse';
    /** danger: a figure that needs attention (failed payments). */
    valueTone?: 'default' | 'danger';
    className?: string;
};

const hintStyles: Record<NonNullable<StatProps['hintTone']>, string> = {
    default: 'text-[var(--text-secondary)]',
    positive: 'text-[var(--herb-700)]',
    warning: 'text-[var(--saffron-700)]',
    negative: 'text-[var(--berry-500)]',
};

export function Stat({
    label,
    value,
    hint,
    hintTone = 'default',
    tone = 'default',
    valueTone = 'default',
    className = '',
}: StatProps) {
    const inverse = tone === 'inverse';

    return (
        <div
            data-tone={tone}
            className={`min-w-0 rounded-[var(--radius-lg)] border p-4 shadow-[var(--shadow-rest)] ${
                inverse
                    ? 'border-transparent bg-[var(--surface-inverse)] text-[var(--text-inverse)]'
                    : 'border-[var(--border-subtle)] bg-[var(--surface-card)]'
            } ${className}`}
        >
            <div
                className={`text-caption ${
                    inverse
                        ? 'text-[var(--ink-300)]'
                        : 'text-[var(--text-tertiary)]'
                }`}
            >
                {label}
            </div>

            <div
                className={`text-display-3 truncate font-mono leading-[1.1] ${
                    inverse
                        ? 'text-[var(--ink-25)]'
                        : valueTone === 'danger'
                          ? 'text-[var(--danger-fg)]'
                          : ''
                }`}
                dir="ltr"
                data-numeric
            >
                {value}
            </div>

            {hint !== undefined && (
                <div
                    className={`text-caption flex items-center gap-1 ${
                        inverse
                            ? 'text-[var(--clay-300)]'
                            : hintStyles[hintTone]
                    }`}
                >
                    {hint}
                </div>
            )}
        </div>
    );
}
