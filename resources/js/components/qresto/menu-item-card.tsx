import { Tag } from '@/components/qresto/tag';
import { cn } from '@/lib/utils';

type MenuItemCardProps = {
    name: string;
    description?: string | null;
    price: string;
    photo?: string | null;
    tags?: string[];
    flag?: string | null;
    available?: boolean;
    onClick?: () => void;
};

const placeholderBackgrounds = [
    'linear-gradient(145deg, var(--clay-300), var(--clay-700))',
    'linear-gradient(145deg, var(--saffron-300), var(--saffron-700))',
    'linear-gradient(145deg, var(--teal-300), var(--teal-700))',
];

function getPlaceholderBackground(name: string): string {
    const index =
        Array.from(name).reduce(
            (total, character) => total + character.charCodeAt(0),
            0,
        ) % placeholderBackgrounds.length;

    return placeholderBackgrounds[index];
}

export function MenuItemCard({
    name,
    description,
    price,
    photo,
    tags = [],
    flag,
    available = true,
    onClick,
}: MenuItemCardProps) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'flex w-full items-start gap-3 py-3 text-start transition-opacity',
                !available && 'opacity-[0.45]',
            )}
            aria-label={name}
        >
            <div className="min-w-0 flex-1 pt-0.5">
                <div className="flex flex-wrap items-center gap-2">
                    <h3 className="text-body-lg font-semibold leading-snug">
                        {name}
                    </h3>

                    {flag && (
                        <span className="text-micro rounded-pill bg-[var(--clay-50)] px-2 py-1 font-semibold text-[var(--clay-700)]">
                            {flag}
                        </span>
                    )}
                </div>

                {description && (
                    <p className="text-caption text-text-secondary mt-1 line-clamp-2 leading-relaxed">
                        {description}
                    </p>
                )}

                <div className="mt-2 flex flex-wrap items-center gap-2">
                    <span className="font-mono text-sm font-medium tabular-nums">
                        {price}
                    </span>

                    {tags.map((tag) => (
                        <Tag key={tag}>{tag}</Tag>
                    ))}

                    {!available && (
                        <span className="text-micro text-text-tertiary font-semibold">
                            Sold out
                        </span>
                    )}
                </div>
            </div>

            {photo ? (
                <img
                    src={photo}
                    alt=""
                    className={cn(
                        'size-[84px] shrink-0 rounded-[var(--radius-container)] object-cover',
                        !available && 'grayscale',
                    )}
                />
            ) : (
                <span
                    aria-hidden="true"
                    className={cn(
                        'block size-[84px] shrink-0 rounded-[var(--radius-container)]',
                        !available && 'grayscale',
                    )}
                    style={{
                        background: getPlaceholderBackground(name),
                    }}
                />
            )}
        </button>
    );
}
