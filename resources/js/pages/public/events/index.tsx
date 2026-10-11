import { Head, Link } from '@inertiajs/react';
import { Calendar } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { Pagination } from '@/components/admin/pagination';
import { StatusBadge } from '@/components/admin/status-badge';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/events';
import type { SimplePaginated } from '@/types';

type EventCard = {
    title: string;
    slug: string;
    category: string;
    status: string;
    starts_at: string;
    venue: string | null;
    community: { name: string; slug: string } | null;
    county: string | null;
};

export default function Events({
    events,
    past,
}: {
    events: SimplePaginated<EventCard>;
    past: boolean;
}) {
    return (
        <>
            <Head title="Events" />
            <div className="mx-auto max-w-4xl space-y-6 px-4 py-10">
                <div className="flex flex-wrap items-end justify-between gap-2">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {past ? 'Past events' : 'Upcoming events'}
                    </h1>
                    <nav className="flex gap-1 rounded-lg bg-muted p-1 text-sm">
                        <Link
                            href={index()}
                            className={cn(
                                'rounded-md px-3 py-1',
                                !past && 'bg-background shadow-sm',
                            )}
                        >
                            Upcoming
                        </Link>
                        <Link
                            href={index({ query: { past: 1 } })}
                            className={cn(
                                'rounded-md px-3 py-1',
                                past && 'bg-background shadow-sm',
                            )}
                        >
                            Past
                        </Link>
                    </nav>
                </div>
                {events.data.length === 0 ? (
                    <EmptyState icon={Calendar} title="No events" />
                ) : (
                    <ul className="divide-y">
                        {events.data.map((event) => (
                            <li key={event.slug} className="py-4">
                                <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                    {new Date(event.starts_at).toLocaleString()}{' '}
                                    · {event.category}
                                    {event.status === 'cancelled' && (
                                        <StatusBadge
                                            status="rejected"
                                            label="Cancelled"
                                        />
                                    )}
                                </div>
                                <Link
                                    href={show(event.slug)}
                                    className="text-lg font-medium hover:underline"
                                >
                                    {event.title}
                                </Link>
                                <div className="text-sm text-muted-foreground">
                                    {[
                                        event.venue,
                                        event.community?.name,
                                        event.county,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
                <Pagination
                    links={events.links}
                    from={events.from}
                    to={events.to}
                    total={events.total}
                />
            </div>
        </>
    );
}
