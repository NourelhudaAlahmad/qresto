type SectionHeadProps = {
    title: React.ReactNode;
    count?: React.ReactNode;
};

export function SectionHead({ title, count }: SectionHeadProps) {
    return (
        <div className="flex items-baseline gap-3">
            <h2 className="flex-1 font-display text-title-1 font-semibold tracking-[var(--tracking-title)]">
                {title}
            </h2>

            {count !== undefined && (
                <span className="font-mono text-caption text-[var(--text-tertiary)]">
                    {count}
                </span>
            )}
        </div>
    );
}