type ChipFilterProps = {
    children: React.ReactNode;
    active?: boolean;
    type?: 'filter' | 'category';
    onClick?: () => void;
};

export function ChipFilter({
    children,
    active = false,
    type = 'filter',
    onClick,
}: ChipFilterProps) {
    const activeClasses =
        type === 'category'
            ? 'border-transparent bg-[var(--clay-500)] text-white'
            : 'border-transparent bg-[var(--ink-900)] text-[var(--ink-25)]';

    const inactiveClasses =
        'border-[var(--border-default)] bg-[var(--surface-card)] text-[var(--text-secondary)]';

    return (
        <button
            type="button"
            onClick={onClick}
            className={`inline-flex min-h-11 items-center justify-center rounded-full border px-3 text-caption font-semibold transition-colors ${
                active ? activeClasses : inactiveClasses
            }`}
            aria-pressed={active}
        >
            {children}
        </button>
    );
}