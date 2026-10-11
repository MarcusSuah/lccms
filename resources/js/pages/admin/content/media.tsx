import { Head, router } from '@inertiajs/react';
import { Images } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { FilterBar } from '@/components/admin/filter-bar';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { StatusBadge, humanize } from '@/components/admin/status-badge';
import { Button } from '@/components/ui/button';
import { approve, index } from '@/routes/admin/content/media';
import type { Option, SimplePaginated } from '@/types';

type Item = {
    uuid: string;
    caption: string | null;
    owner: string | null;
    visibility: string;
    approval_status: string;
    consent_status: string;
    shows_identifiable_people: boolean;
    shows_children: boolean;
    uploaded_by: string | null;
    thumbnail_url: string;
    url: string;
};

export default function MediaLibrary({
    media,
    filters,
    statusOptions,
    can,
}: {
    media: SimplePaginated<Item>;
    filters: { status: string };
    statusOptions: Option[];
    can: { approve: boolean };
}) {
    return (
        <>
            <Head title="Photo approvals" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Public website"
                    title="Photo approvals"
                    description="Approve photographs before they appear publicly. A public photo of identifiable people or children requires recorded consent."
                />
                <FilterBar
                    filters={filters}
                    selects={[
                        {
                            name: 'status',
                            label: 'Status',
                            options: statusOptions,
                        },
                    ]}
                />
                {media.data.length === 0 ? (
                    <EmptyState icon={Images} title="Nothing here" />
                ) : (
                    <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {media.data.map((item) => (
                            <li
                                key={item.uuid}
                                className="space-y-2 rounded-lg border p-3"
                            >
                                <a
                                    href={item.url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <img
                                        src={item.thumbnail_url}
                                        alt=""
                                        className="aspect-video w-full rounded object-cover"
                                        loading="lazy"
                                    />
                                </a>
                                <div className="text-sm font-medium">
                                    {item.owner}
                                </div>
                                {item.caption && (
                                    <p className="text-xs text-muted-foreground">
                                        {item.caption}
                                    </p>
                                )}
                                <div className="flex flex-wrap gap-1">
                                    <StatusBadge
                                        status={item.approval_status}
                                    />
                                    <StatusBadge
                                        status={
                                            item.consent_status === 'obtained'
                                                ? 'approved'
                                                : item.consent_status ===
                                                    'not_required'
                                                  ? 'inactive'
                                                  : 'pending'
                                        }
                                        label={humanize(item.consent_status)}
                                    />
                                    {item.visibility === 'internal' && (
                                        <StatusBadge
                                            status="inactive"
                                            label="Staff only"
                                        />
                                    )}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {[
                                        item.shows_identifiable_people &&
                                            'identifiable people',
                                        item.shows_children && 'children',
                                    ]
                                        .filter(Boolean)
                                        .join(', ') ||
                                        'no people indicated'}{' '}
                                    · by {item.uploaded_by}
                                </p>
                                {can.approve &&
                                    item.approval_status === 'pending' && (
                                        <div className="flex gap-2">
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    router.patch(
                                                        approve.url(item.uuid),
                                                        {
                                                            decision:
                                                                'approved',
                                                        },
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                Approve
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    router.patch(
                                                        approve.url(item.uuid),
                                                        {
                                                            decision:
                                                                'rejected',
                                                        },
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                Reject
                                            </Button>
                                        </div>
                                    )}
                            </li>
                        ))}
                    </ul>
                )}
                <Pagination
                    links={media.links}
                    from={media.from}
                    to={media.to}
                    total={media.total}
                />
            </div>
        </>
    );
}

MediaLibrary.layout = {
    breadcrumbs: [{ title: 'Photo approvals', href: index() }],
};
