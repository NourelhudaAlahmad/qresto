type EmptyStateProps = {
    icon?: React.ReactNode;
    headline: React.ReactNode;
    body?: React.ReactNode;
    className?: string;
};
export function EmptyState({
    icon,
    headline,
    body,
    className = '',
}: EmptyStateProps) {
    return (
        <div
            className={`flex flex-col items-center justify-center px-5 py-8 text-center ${className}`}
        >
            {icon !== undefined && (
                <div className="mb-3 text-[var(--text-tertiary)]">{icon}</div>
            )}

            <h2 className="font-display text-title-2 font-semibold tracking-[var(--tracking-title)]">
                {headline}
            </h2>

            {body !== undefined && (
                <p className="text-body mt-2 max-w-[44ch] text-[var(--text-secondary)]">
                    {body}
                </p>
            )}
        </div>
    );
}
