import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { show } from '@/routes/admin/geography';
import type { GeoBreadcrumb as Crumb } from '@/types';

/**
 * County → … → area trail with links to each ancestor.
 */
export function GeoBreadcrumb({
    trail,
    linkLast = false,
}: {
    trail: Crumb[];
    linkLast?: boolean;
}) {
    return (
        <nav aria-label="Geographic location">
            <ol className="flex flex-wrap items-center gap-1 text-sm text-muted-foreground">
                {trail.map((crumb, position) => {
                    const isLast = position === trail.length - 1;

                    return (
                        <li
                            key={`${crumb.level}-${crumb.id}`}
                            className="flex items-center gap-1"
                        >
                            {isLast && !linkLast ? (
                                <span className="font-medium text-foreground">
                                    {crumb.name}
                                </span>
                            ) : (
                                <Link
                                    href={show({
                                        level: crumb.level,
                                        geoArea: crumb.id,
                                    })}
                                    className="hover:text-foreground hover:underline"
                                >
                                    {crumb.name}
                                </Link>
                            )}
                            {!isLast && (
                                <ChevronRight
                                    className="size-3.5"
                                    aria-hidden
                                />
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
