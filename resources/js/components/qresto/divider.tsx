type DividerProps = {
    className?: string;
};

export function Divider({ className = '' }: DividerProps) {
    return (
        <div
            role="separator"
            className={`h-px w-full bg-[var(--border-subtle)] ${className}`}
        />
    );
}