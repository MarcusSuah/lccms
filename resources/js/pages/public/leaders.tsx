import { Head, Link } from '@inertiajs/react';
import { EmptyState } from '@/components/admin/empty-state';
import { FilterBar } from '@/components/admin/filter-bar';
import { Pagination } from '@/components/admin/pagination';
import { show } from '@/routes/communities';
import type { SimplePaginated } from '@/types';

type Leader = {
    name: string;
    position: string | null;
    block: string | null;
    starts_on: string;
    selection_method: string;
    community: string | null;
    community_slug: string | null;
};

export default function Leaders({
    tenures,
    filters,
}: {
    tenures: SimplePaginated<Leader>;
    filters: { search: string };
}) {
    return (
        <>
            <Head title="Leadership directory" />
            <div className="mx-auto max-w-6xl space-y-6 px-4 py-10">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Leadership directory
                    </h1>
                    <p className="text-muted-foreground">
                        Current, verified community and block leaders. Contact
                        details are not published.
                    </p>
                </div>
                <FilterBar
                    filters={filters}
                    searchPlaceholder="Leader or community"
                />
                {tenures.data.length === 0 ? (
                    <EmptyState title="No leaders listed" />
                ) : (
                    <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {tenures.data.map((leader) => (
                            <li
                                key={`${leader.community_slug}-${leader.position}-${leader.name}`}
                                className="rounded-lg border p-4"
                            >
                                <div className="font-medium">{leader.name}</div>
                                <div className="text-sm">{leader.position}</div>
                                <div className="text-xs text-muted-foreground">
                                    {leader.community_slug ? (
                                        <Link
                                            href={show(leader.community_slug)}
                                            className="hover:underline"
                                        >
                                            {leader.community}
                                        </Link>
                                    ) : (
                                        leader.community
                                    )}
                                    {leader.block && ` › ${leader.block}`} ·
                                    since {leader.starts_on}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
                <Pagination
                    links={tenures.links}
                    from={tenures.from}
                    to={tenures.to}
                    total={tenures.total}
                />
            </div>
        </>
    );
}
