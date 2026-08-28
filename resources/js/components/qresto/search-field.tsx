import { SearchIcon } from 'lucide-react';
import * as React from 'react';

import { Input } from '@/components/qresto/input';
import { cn } from '@/lib/utils';

type SearchFieldProps = React.ComponentProps<typeof Input>;

function SearchField({ className, ...props }: SearchFieldProps) {
    return (
        <div
            data-slot="qresto-search-field"
            className={cn(
                'relative flex min-h-[44px] w-full items-center',
                className,
            )}
        >
            <SearchIcon
                aria-hidden="true"
                className="text-text-tertiary pointer-events-none absolute left-3 size-4"
            />

            <Input
                {...props}
                type="search"
                className="pl-10"
                aria-label={props['aria-label'] ?? 'Search'}
            />
        </div>
    );
}

export { SearchField };
