import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { PaginationLink } from '@/types';

/**
 * Server-side pagination links from a Laravel paginator.
 */
export function Pagination({
    links,
    from,
    to,
    total,
}: {
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
}) {
    if (total === 0) {
        return null;
    }

    return (
        <nav
            aria-label="Pagination"
            className="flex flex-col items-center justify-between gap-3 text-sm sm:flex-row"
        >
            <p className="text-muted-foreground">
                Showing {from ?? 0}–{to ?? 0} of {total.toLocaleString()}
            </p>
            {links.length > 3 && (
                <ul className="flex flex-wrap items-center gap-1">
                    {links.map((link, index) => {
                        const label = link.label
                            .replace('&laquo;', '‹')
                            .replace('&raquo;', '›');

                        return (
                            <li key={`${link.label}-${index}`}>
                                {link.url ? (
                                    <Link
                                        href={link.url}
                                        preserveScroll
                                        preserveState
                                        aria-current={
                                            link.active ? 'page' : undefined
                                        }
                                        className={cn(
                                            'inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 transition-colors hover:bg-accent',
                                            link.active &&
                                                'border-primary bg-primary text-primary-foreground hover:bg-primary/90',
                                        )}
                                    >
                                        {label}
                                    </Link>
                                ) : (
                                    <span className="inline-flex h-8 min-w-8 items-center justify-center px-2 text-muted-foreground">
                                        {label}
                                    </span>
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}
        </nav>
    );
}
