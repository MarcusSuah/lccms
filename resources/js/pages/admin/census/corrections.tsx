import { Head, Link, router } from '@inertiajs/react';
import { FilePenLine } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { FilterBar } from '@/components/admin/filter-bar';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { StatusBadge, humanize } from '@/components/admin/status-badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index, update } from '@/routes/admin/census/corrections';
import { show as showHousehold } from '@/routes/admin/census/households';
import type { Option, SimplePaginated } from '@/types';

type CorrectionRow = {
    id: number;
    record_reference: string;
    household_id: number;
    type: string;
    fields: string[];
    reason: string;
    status: string;
    requested_by: string | null;
    requested_by_me: boolean;
    decided_by: string | null;
    created_at: string | null;
};

export default function Corrections({
    corrections,
    filters,
    statusOptions,
    can,
}: {
    corrections: SimplePaginated<CorrectionRow>;
    filters: { status: string };
    statusOptions: Option[];
    can: { decide: boolean };
}) {
    const decide = (id: number, decision: 'approve' | 'reject') => {
        const note =
            decision === 'reject'
                ? window.prompt('Reason for rejecting')
                : (window.prompt('Decision note (optional)') ?? '');

        if (decision === 'reject' && !note) {
            return;
        }

        router.patch(
            update.url(id),
            { decision, note },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Corrections" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Census"
                    title="Corrections"
                    description="Changes to verified or approved records. Each one is applied only after a second authorised person approves it, and every change is recorded."
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
                {corrections.data.length === 0 ? (
                    <EmptyState icon={FilePenLine} title="No corrections" />
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Record</TableHead>
                                    <TableHead>Fields</TableHead>
                                    <TableHead>Reason</TableHead>
                                    <TableHead>Requested by</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="sr-only">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {corrections.data.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell>
                                            <Link
                                                href={showHousehold(
                                                    row.household_id,
                                                )}
                                                className="font-mono text-xs hover:underline"
                                            >
                                                {row.record_reference}
                                            </Link>
                                            <div className="text-xs text-muted-foreground">
                                                {row.type}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {row.fields
                                                .map(humanize)
                                                .join(', ')}
                                        </TableCell>
                                        <TableCell className="max-w-xs text-sm">
                                            {row.reason}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {row.requested_by}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge status={row.status} />
                                        </TableCell>
                                        <TableCell className="text-right whitespace-nowrap">
                                            {can.decide &&
                                                row.status === 'pending' &&
                                                !row.requested_by_me && (
                                                    <div className="flex justify-end gap-2">
                                                        <Button
                                                            size="sm"
                                                            onClick={() =>
                                                                decide(
                                                                    row.id,
                                                                    'approve',
                                                                )
                                                            }
                                                        >
                                                            Approve
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                decide(
                                                                    row.id,
                                                                    'reject',
                                                                )
                                                            }
                                                        >
                                                            Reject
                                                        </Button>
                                                    </div>
                                                )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
                <Pagination
                    links={corrections.links}
                    from={corrections.from}
                    to={corrections.to}
                    total={corrections.total}
                />
            </div>
        </>
    );
}

Corrections.layout = {
    breadcrumbs: [{ title: 'Corrections', href: index() }],
};
