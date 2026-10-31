type BadgeProps = {
    children: React.ReactNode;
    className?: string;
};

export function Badge({ children, className = '' }: BadgeProps) {
    return (
        <span
            className={`text-micro inline-flex min-w-5 items-center justify-center rounded-[var(--radius-pill)] bg-[var(--clay-500)] px-1.5 font-mono text-white ${className}`}
        >
            {children}
        </span>
    );
}
