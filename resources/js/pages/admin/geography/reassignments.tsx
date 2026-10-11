import { Head, router } from '@inertiajs/react';
import { ArrowRightLeft } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import { FilterBar } from '@/components/admin/filter-bar';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { StatusBadge } from '@/components/admin/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { index, update } from '@/routes/admin/geography/reassignments';
import type { Option, SimplePaginated } from '@/types';

type Reassignment = {
    id: number;
    level: string;
    area_name: string;
    area_reference_code: string;
    reason: string;
    effective_on: string;
    status: string;
    requested_by: string | null;
    decided_by: string | null;
    decision_note: string | null;
    affected_counts: Record<string, number> | null;
    created_at: string | null;
};

function Decision({ item }: { item: Reassignment }) {
    const [note, setNote] = useState('');

    const decide = (decision: 'approve' | 'reject') =>
        router.patch(
            update.url(item.id),
            { decision, note },
            { preserveScroll: true },
        );

    return (
        <div className="space-y-2">
            <Textarea
                aria-label="Decision note"
                placeholder="Decision note (required to reject)"
                value={note}
                onChange={(event) => setNote(event.target.value)}
            />
            <div className="flex gap-2">
                <Button size="sm" onClick={() => decide('approve')}>
                    Approve and apply
                </Button>
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => decide('reject')}
                >
                    Reject
                </Button>
            </div>
        </div>
    );
}

export default function Reassignments({
    reassignments,
    filters,
    statusOptions,
    can,
}: {
    reassignments: SimplePaginated<Reassignment>;
    filters: { status: string };
    statusOptions: Option[];
    can: { decide: boolean };
}) {
    return (
        <>
            <Head title="Reassignments" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Geographic registry"
                    title="Reassignment requests"
                    description="Moves take effect only after a second authorised person approves them. Approved census results of closed campaigns are never relocated."
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
                {reassignments.data.length === 0 ? (
                    <EmptyState
                        icon={ArrowRightLeft}
                        title="No reassignment requests"
                    />
                ) : (
                    <div className="grid gap-4">
                        {reassignments.data.map((item) => (
                            <Card key={item.id}>
                                <CardContent className="grid gap-4 pt-6 md:grid-cols-[1fr_minmax(0,22rem)]">
                                    <div className="space-y-1 text-sm">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <StatusBadge status={item.status} />
                                            <span className="font-medium capitalize">
                                                {item.level}: {item.area_name}
                                            </span>
                                            <span className="font-mono text-xs text-muted-foreground">
                                                {item.area_reference_code}
                                            </span>
                                        </div>
                                        <p>{item.reason}</p>
                                        <p className="text-xs text-muted-foreground">
                                            Effective {item.effective_on} ·
                                            requested by {item.requested_by}
                                            {item.decided_by &&
                                                ` · decided by ${item.decided_by}`}
                                        </p>
                                        {item.decision_note && (
                                            <p className="text-xs">
                                                Note: {item.decision_note}
                                            </p>
                                        )}
                                        {item.affected_counts && (
                                            <p className="text-xs text-muted-foreground">
                                                Rows updated:{' '}
                                                {Object.entries(
                                                    item.affected_counts,
                                                )
                                                    .map(
                                                        ([table, count]) =>
                                                            `${table} ${count}`,
                                                    )
                                                    .join(', ')}
                                            </p>
                                        )}
                                    </div>
                                    {can.decide &&
                                        item.status === 'pending' && (
                                            <Decision item={item} />
                                        )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
                <Pagination
                    links={reassignments.links}
                    from={reassignments.from}
                    to={reassignments.to}
                    total={reassignments.total}
                />
            </div>
        </>
    );
}

Reassignments.layout = {
    breadcrumbs: [{ title: 'Reassignments', href: index() }],
};
