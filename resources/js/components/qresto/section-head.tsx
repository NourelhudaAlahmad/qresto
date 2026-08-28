type SectionHeadProps = {
    title: React.ReactNode;
    count?: React.ReactNode;
};

export function SectionHead({ title, count }: SectionHeadProps) {
    return (
        <div className="flex items-baseline gap-3">
            <h2 className="font-display text-title-1 flex-1 font-semibold tracking-[var(--tracking-title)]">
                {title}
            </h2>

            {count !== undefined && (
                <span className="text-caption font-mono text-[var(--text-tertiary)]">
                    {count}
                </span>
            )}
        </div>
    );
}
