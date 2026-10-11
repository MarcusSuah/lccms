import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import type { Option } from '@/types';

export type FilterSelect = {
    name: string;
    label: string;
    options: Option<string | number>[];
};

/**
 * Search box and select filters that update the query string. The server
 * performs all filtering and pagination.
 */
export function FilterBar({
    filters,
    selects = [],
    searchPlaceholder = 'Search…',
}: {
    filters: Record<string, string | number | null | undefined>;
    selects?: FilterSelect[];
    searchPlaceholder?: string;
}) {
    const [search, setSearch] = useState(String(filters.search ?? ''));
    const firstRender = useRef(true);

    const apply = (changes: Record<string, string | number | null>) => {
        const query: Record<string, string | number> = {};

        for (const [key, value] of Object.entries({ ...filters, ...changes })) {
            if (value !== null && value !== undefined && value !== '') {
                query[key] = value;
            }
        }

        router.get(window.location.pathname, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timeout = window.setTimeout(() => apply({ search }), 350);

        return () => window.clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    return (
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
            <div className="relative w-full sm:max-w-xs">
                <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    type="search"
                    aria-label="Search"
                    placeholder={searchPlaceholder}
                    className="pl-8"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                />
            </div>
            {selects.map((select) => (
                <NativeSelect
                    key={select.name}
                    aria-label={select.label}
                    className="sm:w-48"
                    value={String(filters[select.name] ?? '')}
                    onChange={(event) =>
                        apply({ [select.name]: event.target.value })
                    }
                >
                    <option value="">{select.label}: all</option>
                    {select.options.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </NativeSelect>
            ))}
        </div>
    );
}
