type TagProps = {
    children: React.ReactNode;
    className?: string;
};

export function Tag({ children, className = '' }: TagProps) {
    return (
        <span
            className={`inline-flex h-5 items-center rounded-[var(--radius-xs)] bg-[var(--herb-50)] px-2 text-micro font-medium text-[var(--herb-700)] ${className}`}
        >
            {children}
        </span>
    );
}