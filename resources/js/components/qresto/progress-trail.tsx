import type { ReactNode } from 'react';

type ProgressTrailItem = {
    title: ReactNode;
    time?: ReactNode;
    detail?: ReactNode;
    state: 'done' | 'current' | 'upcoming';
};

type ProgressTrailProps = {
    items: ProgressTrailItem[];
    className?: string;
};

const stateStyles = {
    done: {
        dot: 'bg-[var(--herb-500)] border-[var(--herb-500)]',
        line: 'bg-[var(--herb-500)]',
        title: 'text-[var(--text-primary)]',
    },
    current: {
        dot: 'bg-[var(--clay-500)] border-[var(--clay-500)]',
        line: 'bg-[var(--border-subtle)]',
        title: 'text-[var(--text-primary)]',
    },
    upcoming: {
        dot: 'bg-[var(--ink-25)] border-[var(--ink-200)]',
        line: 'bg-[var(--border-subtle)]',
        title: 'text-[var(--text-tertiary)]',
    },
};

export function ProgressTrail({
    items,
    className = '',
}: ProgressTrailProps) {
    return (
        <div className={`flex flex-col ${className}`}>
            {items.map((item, index) => {
                const styles = stateStyles[item.state];
                const isLast = index === items.length - 1;

                return (
                    <div
                        key={index}
                        className="flex items-stretch gap-4"
                    >
                        <div className="flex w-5 shrink-0 flex-col items-center">
                            <span
                                className={`mt-1 h-3 w-3 rounded-full border-2 ${styles.dot}`}
                            />

                            {!isLast && (
                                <span
                                    className={`mt-1 w-px flex-1 ${styles.line}`}
                                />
                            )}
                        </div>

                        <div className="min-w-0 flex-1 pb-5">
                            <div className="flex items-baseline gap-2">
                                <span
                                    className={`font-display text-body font-semibold ${styles.title}`}
                                >
                                    {item.title}
                                </span>

                                {item.time !== undefined && (
                                    <span className="font-mono text-caption text-[var(--text-tertiary)]">
                                        {item.time}
                                    </span>
                                )}
                            </div>

                            {item.detail !== undefined && (
                                <p className="mt-1 text-caption leading-[var(--leading-normal)] text-[var(--text-secondary)]">
                                    {item.detail}
                                </p>
                            )}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}