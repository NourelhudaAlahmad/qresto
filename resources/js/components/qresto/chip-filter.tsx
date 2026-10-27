type ChipFilterProps = {
    children: React.ReactNode;
    active?: boolean;
    /**
     * filter: herb when active (guest filters).
     * category: clay when active (menu categories).
     * segment: ink-900 when active (staff status filters and view switches).
     */
    type?: 'filter' | 'category' | 'segment';
    onClick?: () => void;
};

export function ChipFilter({
    children,
    active = false,
    type = 'filter',
    onClick,
}: ChipFilterProps) {
    const activeClasses = {
        category: 'border-transparent bg-[var(--clay-500)] !text-white',
        filter: 'border-[var(--herb-300)] bg-[var(--herb-50)] text-[var(--herb-700)]',
        segment: 'border-transparent bg-[var(--ink-900)] !text-[var(--ink-0)]',
    }[type];

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
