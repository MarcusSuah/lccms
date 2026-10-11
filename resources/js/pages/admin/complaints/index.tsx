import { Head, Link } from '@inertiajs/react';
import { MessageSquareWarning, Paperclip } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { FilterBar } from '@/components/admin/filter-bar';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { StatusBadge } from '@/components/admin/status-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index, show } from '@/routes/admin/complaints';
import type { Option, SimplePaginated } from '@/types';

type Row = {
    reference: string;
    subject: string;
    category: string;
    status: string;
    priority: string;
    area: string;
    assignee: string | null;
    attachments: number;
    created_at: string | null;
};

export default function ComplaintsIndex({
    complaints,
    filters,
    statusOptions,
    categoryOptions,
}: {
    complaints: SimplePaginated<Row>;
    filters: Record<string, string | number | boolean>;
    statusOptions: Option[];
    categoryOptions: Option[];
}) {
    return (
        <>
            <Head title="Complaints" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Public services"
                    title="Complaints"
                    description="Complaints from the public within your areas. Contact details are encrypted and shown only on the complaint page."
                />
                <FilterBar
                    filters={{ ...filters, mine: filters.mine ? 1 : '' }}
                    searchPlaceholder="Reference or subject"
                    selects={[
                        {
                            name: 'status',
                            label: 'Status',
                            options: statusOptions,
                        },
                        {
                            name: 'category',
                            label: 'Category',
                            options: categoryOptions,
                        },
                        {
                            name: 'mine',
                            label: 'Assigned',
                            options: [{ value: 1, label: 'Assigned to me' }],
                        },
                    ]}
                />
                {complaints.data.length === 0 ? (
                    <EmptyState
                        icon={MessageSquareWarning}
                        title="No complaints"
                    />
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Complaint</TableHead>
                                    <TableHead>Area</TableHead>
                                    <TableHead>Assigned to</TableHead>
                                    <TableHead>Received</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {complaints.data.map((complaint) => (
                                    <TableRow key={complaint.reference}>
                                        <TableCell>
                                            <Link
                                                href={show(complaint.reference)}
                                                className="font-medium hover:underline"
                                            >
                                                {complaint.subject}
                                            </Link>
                                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                <span className="font-mono">
                                                    {complaint.reference}
                                                </span>{' '}
                                                · {complaint.category}
                                                {complaint.attachments > 0 && (
                                                    <span className="flex items-center gap-0.5">
                                                        <Paperclip className="size-3" />{' '}
                                                        {complaint.attachments}
                                                    </span>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {complaint.area}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {complaint.assignee ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {complaint.created_at &&
                                                new Date(
                                                    complaint.created_at,
                                                ).toLocaleDateString()}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                <StatusBadge
                                                    status={complaint.status}
                                                />
                                                {(complaint.priority ===
                                                    'urgent' ||
                                                    complaint.priority ===
                                                        'high') && (
                                                    <StatusBadge
                                                        status="rejected"
                                                        label={
                                                            complaint.priority
                                                        }
                                                    />
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
                <Pagination
                    links={complaints.links}
                    from={complaints.from}
                    to={complaints.to}
                    total={complaints.total}
                />
            </div>
        </>
    );
}

ComplaintsIndex.layout = {
    breadcrumbs: [{ title: 'Complaints', href: index() }],
};
