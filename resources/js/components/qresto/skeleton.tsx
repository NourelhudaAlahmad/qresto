type SkeletonProps = {
    width?: string;
    height?: string;
    className?: string;
};

export function Skeleton({
    width = '100%',
    height = '12px',
    className = '',
}: SkeletonProps) {
    return (
        <span
            aria-hidden="true"
            className={`block rounded-[var(--radius-pill)] ${className}`}
            style={{
                width,
                height,
                background:
                    'linear-gradient(90deg, var(--ink-100) 25%, var(--ink-50) 50%, var(--ink-100) 75%)',
                backgroundSize: '200% 100%',
                animation: 'qr-shimmer 1.4s linear infinite',
            }}
        />
    );
}