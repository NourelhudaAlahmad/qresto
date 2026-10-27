type AvatarProps = {
    initials: string;
    size?: 'sm' | 'md';
    tone?: 'clay' | 'teal' | 'ink';
    className?: string;
};

const tones = {
    clay: 'bg-[var(--clay-100)] text-[var(--clay-700)]',
    teal: 'bg-[var(--teal-100)] text-[var(--teal-700)]',
    ink: 'bg-[var(--ink-100)] text-[var(--ink-700)]',
};

const sizes = {
    sm: 'h-8 w-8',
    md: 'h-9 w-9',
};

export function Avatar({
    initials,
    size = 'md',
    tone = 'clay',
    className = '',
}: AvatarProps) {
    return (
        <span
            className={`font-display text-caption grid shrink-0 place-items-center rounded-full font-semibold ${tones[tone]} ${sizes[size]} ${className}`}
            aria-label={initials}
        >
            {initials}
        </span>
    );
}
