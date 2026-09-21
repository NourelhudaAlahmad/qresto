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
            : 'border-[var(--herb-300)] bg-[var(--herb-50)] text-[var(--herb-700)]';

    const inactiveClasses =
        'border-[var(--border-default)] bg-[var(--surface-card)] text-[var(--text-secondary)]';

    return (
        <button
            type="button"
            onClick={onClick}
            className={`text-caption inline-flex min-h-11 shrink-0 items-center justify-center rounded-full border px-3 font-semibold transition-colors ${
                active ? activeClasses : inactiveClasses
            }`}
            aria-pressed={active}
        >
            {children}
        </button>
    );
}
