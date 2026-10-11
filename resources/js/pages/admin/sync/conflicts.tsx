import { Head, Link, router } from '@inertiajs/react';
import { GitMerge } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { FilterBar } from '@/components/admin/filter-bar';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { StatusBadge, humanize } from '@/components/admin/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { show as showHousehold } from '@/routes/admin/census/households';
import { index, update } from '@/routes/admin/sync/conflicts';
import type { Option, SimplePaginated } from '@/types';

type Conflict = {
    id: number;
    entity_type: string;
    entity_id: number;
    conflict_class: string;
    conflict_label: string;
    base_version: number | null;
    server_version: number;
    server: Record<string, unknown>;
    submitted: Record<string, unknown>;
    raised_by: string | null;
    resolved_by: string | null;
    resolution_note: string | null;
    status: string;
    created_at: string | null;
    household_id: number | null;
};

const ignored = new Set([
    'house_uuid',
    'household_uuid',
    'block_id',
    'version',
    'status',
]);

function format(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }

    return typeof value === 'object'
        ? JSON.stringify(value)
        : String(value as string | number);
}

function Comparison({ conflict }: { conflict: Conflict }) {
    const fields = Object.keys(conflict.submitted).filter(
        (field) => !ignored.has(field),
    );

    if (fields.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                A status change ({conflict.entity_type} submission).
            </p>
        );
    }

    return (
        <table className="w-full text-sm">
            <thead>
                <tr className="text-left text-xs text-muted-foreground">
                    <th className="py-1 font-medium">Field</th>
                    <th className="py-1 font-medium">
                        On the server (v{conflict.server_version})
                    </th>
                    <th className="py-1 font-medium">
                        From the device (based on v
                        {conflict.base_version ?? '?'})
                    </th>
                </tr>
            </thead>
            <tbody>
                {fields.map((field) => {
                    const differs =
                        format(conflict.server[field]) !==
                        format(conflict.submitted[field]);

                    return (
                        <tr
                            key={field}
                            className={
                                differs
                                    ? 'font-medium'
                                    : 'text-muted-foreground'
                            }
                        >
                            <td className="py-1 pr-2">{humanize(field)}</td>
                            <td className="py-1 pr-2">
                                {format(conflict.server[field])}
                            </td>
                            <td className="py-1">
                                {format(conflict.submitted[field])}
                            </td>
                        </tr>
                    );
                })}
            </tbody>
        </table>
    );
}

export default function SyncConflicts({
    conflicts,
    filters,
    statusOptions,
    personalDataHidden,
}: {
    conflicts: SimplePaginated<Conflict>;
    filters: { status: string };
    statusOptions: Option[];
    personalDataHidden: boolean;
}) {
    const resolve = (id: number, decision: string) => {
        const note = window.prompt('Reason for this decision');

        if (note) {
            router.patch(
                update.url(id),
                { decision, note },
                { preserveScroll: true },
            );
        }
    };

    return (
        <>
            <Head title="Sync conflicts" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Field operations"
                    title="Synchronisation conflicts"
                    description="Offline changes that arrived after the record had changed on the server. Nothing was overwritten: choose which version to keep. Reviewed records can only change through a correction request."
                />
                {personalDataHidden && (
                    <p className="text-sm text-muted-foreground">
                        Personal fields are hidden for your role.
                    </p>
                )}
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
                {conflicts.data.length === 0 ? (
                    <EmptyState icon={GitMerge} title="No conflicts" />
                ) : (
                    <div className="grid gap-4">
                        {conflicts.data.map((conflict) => (
                            <Card key={conflict.id}>
                                <CardContent className="space-y-3 pt-6">
                                    <div className="flex flex-wrap items-center gap-2 text-sm">
                                        <StatusBadge status={conflict.status} />
                                        <span className="font-medium capitalize">
                                            {conflict.entity_type}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {conflict.conflict_label}
                                        </span>
                                        <span className="text-muted-foreground">
                                            · from {conflict.raised_by}
                                        </span>
                                        {conflict.household_id && (
                                            <Link
                                                href={showHousehold(
                                                    conflict.household_id,
                                                )}
                                                className="text-xs hover:underline"
                                            >
                                                Open household
                                            </Link>
                                        )}
                                    </div>
                                    <Comparison conflict={conflict} />
                                    {conflict.status === 'open' ? (
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    resolve(
                                                        conflict.id,
                                                        'keep_server',
                                                    )
                                                }
                                            >
                                                Keep server version
                                            </Button>
                                            {conflict.conflict_class !==
                                                'edit_after_review' && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        resolve(
                                                            conflict.id,
                                                            'apply_client',
                                                        )
                                                    }
                                                >
                                                    Apply device version
                                                </Button>
                                            )}
                                            {(conflict.entity_type ===
                                                'household' ||
                                                conflict.entity_type ===
                                                    'occupant') && (
                                                <Button
                                                    size="sm"
                                                    onClick={() =>
                                                        resolve(
                                                            conflict.id,
                                                            'convert_correction',
                                                        )
                                                    }
                                                >
                                                    Send as correction request
                                                </Button>
                                            )}
                                        </div>
                                    ) : (
                                        <p className="text-xs text-muted-foreground">
                                            Resolved by {conflict.resolved_by}:{' '}
                                            {conflict.resolution_note}
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
                <Pagination
                    links={conflicts.links}
                    from={conflicts.from}
                    to={conflicts.to}
                    total={conflicts.total}
                />
            </div>
        </>
    );
}

SyncConflicts.layout = {
    breadcrumbs: [{ title: 'Sync conflicts', href: index() }],
};
