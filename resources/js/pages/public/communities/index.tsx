import { Head, Link } from '@inertiajs/react';
import { MapPin } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { FilterBar } from '@/components/admin/filter-bar';
import { Pagination } from '@/components/admin/pagination';
import { show } from '@/routes/communities';
import type { Option, SimplePaginated } from '@/types';

type CommunityCard = {
    name: string;
    slug: string;
    location: string;
    photo: { thumbnail_url: string; alt: string } | null;
};

export default function Communities({
    communities,
    counties,
    filters,
}: {
    communities: SimplePaginated<CommunityCard>;
    counties: Option<number>[];
    filters: { search: string; county: string | number };
}) {
    return (
        <>
            <Head title="Communities" />
            <div className="mx-auto max-w-6xl space-y-6 px-4 py-10">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Communities
                    </h1>
                    <p className="text-muted-foreground">
                        Participating communities with a published profile.
                    </p>
                </div>
                <FilterBar
                    filters={filters}
                    searchPlaceholder="Community name or code"
                    selects={[
                        { name: 'county', label: 'County', options: counties },
                    ]}
                />
                {communities.data.length === 0 ? (
                    <EmptyState title="No communities found" />
                ) : (
                    <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {communities.data.map((community) => (
                            <li key={community.slug}>
                                <Link
                                    href={show(community.slug)}
                                    className="flex h-full flex-col overflow-hidden rounded-lg border hover:shadow-sm"
                                >
                                    {community.photo ? (
                                        <img
                                            src={community.photo.thumbnail_url}
                                            alt={community.photo.alt}
                                            className="aspect-video w-full object-cover"
                                            loading="lazy"
                                        />
                                    ) : (
                                        <div className="flex aspect-video items-center justify-center bg-muted text-sm text-muted-foreground">
                                            No photo yet
                                        </div>
                                    )}
                                    <div className="p-3">
                                        <div className="font-medium">
                                            {community.name}
                                        </div>
                                        <div className="flex items-center gap-1 text-xs text-muted-foreground">
                                            <MapPin className="size-3" />{' '}
                                            {community.location}
                                        </div>
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
                <Pagination
                    links={communities.links}
                    from={communities.from}
                    to={communities.to}
                    total={communities.total}
                />
            </div>
        </>
    );
}
