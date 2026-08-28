type AvatarProps = {
    initials: string;
    size?: 'sm' | 'md';
    className?: string;
};

const sizes = {
    sm: 'h-8 w-8',
    md: 'h-9 w-9',
};

export function Avatar({
    initials,
    size = 'md',
    className = '',
}: AvatarProps) {
    return (
        <span
            className={`grid shrink-0 place-items-center rounded-full bg-[var(--clay-100)] font-display text-caption font-semibold text-[var(--clay-700)] ${sizes[size]} ${className}`}
            aria-label={initials}
        >
            {initials}
        </span>
    );
}