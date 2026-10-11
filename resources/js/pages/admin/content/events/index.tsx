import { Head, Link, router } from '@inertiajs/react';
import { CalendarDays, Plus } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { FilterBar } from '@/components/admin/filter-bar';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { StatusBadge } from '@/components/admin/status-badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    create,
    edit,
    index,
    status as statusRoute,
} from '@/routes/admin/content/events';
import type { Option, SimplePaginated } from '@/types';

type Row = {
    id: number;
    title: string;
    status: string;
    category: string;
    starts_at: string;
    area: string;
};

export default function EventsIndex({
    events,
    filters,
    statusOptions,
    can,
}: {
    events: SimplePaginated<Row>;
    filters: { status: string };
    statusOptions: Option[];
    can: { publish: boolean };
}) {
    return (
        <>
            <Head title="Events" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Public website"
                    title="Events"
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> New event
                            </Link>
                        </Button>
                    }
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
                {events.data.length === 0 ? (
                    <EmptyState icon={CalendarDays} title="No events" />
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Event</TableHead>
                                    <TableHead>When</TableHead>
                                    <TableHead>Area</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="sr-only">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {events.data.map((event) => (
                                    <TableRow key={event.id}>
                                        <TableCell>
                                            <Link
                                                href={edit(event.id)}
                                                className="font-medium hover:underline"
                                            >
                                                {event.title}
                                            </Link>
                                            <div className="text-xs text-muted-foreground">
                                                {event.category}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {new Date(
                                                event.starts_at,
                                            ).toLocaleString()}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {event.area}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                status={event.status}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right whitespace-nowrap">
                                            {can.publish &&
                                                event.status !==
                                                    'published' && (
                                                    <Button
                                                        size="sm"
                                                        onClick={() =>
                                                            router.patch(
                                                                statusRoute.url(
                                                                    event.id,
                                                                ),
                                                                {
                                                                    status: 'published',
                                                                },
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        Approve
                                                    </Button>
                                                )}
                                            {can.publish &&
                                                event.status ===
                                                    'published' && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            router.patch(
                                                                statusRoute.url(
                                                                    event.id,
                                                                ),
                                                                {
                                                                    status: 'cancelled',
                                                                },
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        Cancel event
                                                    </Button>
                                                )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
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

EventsIndex.layout = {
    breadcrumbs: [{ title: 'Events', href: index() }],
};
