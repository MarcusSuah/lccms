import { Head, Link } from '@inertiajs/react';
import { Crown, FileText, Plus, Settings2 } from 'lucide-react';
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
    document as documentRoute,
    edit,
    index,
} from '@/routes/admin/leadership';
import { index as positionsIndex } from '@/routes/admin/leadership/positions';
import type { Option, SimplePaginated } from '@/types';

type Tenure = {
    id: number;
    position: string | null;
    community_name: string | null;
    block_name: string | null;
    holder_name: string;
    starts_on: string;
    ends_on: string | null;
    is_current: boolean;
    verification_status: string;
    is_public: boolean;
    has_exception: boolean;
    has_document: boolean;
};

export default function LeadershipIndex({
    tenures,
    filters,
    positions,
    can,
}: {
    tenures: SimplePaginated<Tenure>;
    filters: Record<string, string>;
    positions: Option<number>[];
    can: { manage: boolean; configure: boolean };
}) {
    return (
        <>
            <Head title="Leadership" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Community governance"
                    title="Leadership history"
                    description="Current and former leaders. Records are never deleted when a new leader takes office; the previous tenure is closed."
                    actions={
                        <>
                            {can.configure && (
                                <Button asChild variant="outline">
                                    <Link href={positionsIndex()}>
                                        <Settings2 /> Positions
                                    </Link>
                                </Button>
                            )}
                            {can.manage && (
                                <Button asChild>
                                    <Link href={create()}>
                                        <Plus /> Record leader
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />
                <FilterBar
                    filters={filters}
                    searchPlaceholder="Leader or community"
                    selects={[
                        {
                            name: 'position',
                            label: 'Position',
                            options: positions,
                        },
                        {
                            name: 'period',
                            label: 'Period',
                            options: [
                                { value: 'current', label: 'Current' },
                                { value: 'former', label: 'Former' },
                            ],
                        },
                    ]}
                />
                {tenures.data.length === 0 ? (
                    <EmptyState icon={Crown} title="No leadership records" />
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Leader</TableHead>
                                    <TableHead>Position</TableHead>
                                    <TableHead>Area</TableHead>
                                    <TableHead>Tenure</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="sr-only">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {tenures.data.map((tenure) => (
                                    <TableRow key={tenure.id}>
                                        <TableCell className="font-medium">
                                            {tenure.holder_name}
                                            {tenure.has_exception && (
                                                <div className="text-xs text-amber-700 dark:text-amber-400">
                                                    Authorised overlap
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {tenure.position}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {tenure.community_name}
                                            {tenure.block_name &&
                                                ` › ${tenure.block_name}`}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {tenure.starts_on} –{' '}
                                            {tenure.ends_on ?? 'present'}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                <StatusBadge
                                                    status={
                                                        tenure.is_current
                                                            ? 'active'
                                                            : 'archived'
                                                    }
                                                    label={
                                                        tenure.is_current
                                                            ? 'Current'
                                                            : 'Former'
                                                    }
                                                />
                                                <StatusBadge
                                                    status={
                                                        tenure.verification_status
                                                    }
                                                />
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-right whitespace-nowrap">
                                            {tenure.has_document &&
                                                can.manage && (
                                                    <Button
                                                        asChild
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label="Supporting document"
                                                    >
                                                        <a
                                                            href={documentRoute.url(
                                                                tenure.id,
                                                            )}
                                                        >
                                                            <FileText />
                                                        </a>
                                                    </Button>
                                                )}
                                            {can.manage && (
                                                <Button
                                                    asChild
                                                    variant="ghost"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={edit(tenure.id)}
                                                    >
                                                        Edit
                                                    </Link>
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
                    links={tenures.links}
                    from={tenures.from}
                    to={tenures.to}
                    total={tenures.total}
                />
            </div>
        </>
    );
}

LeadershipIndex.layout = {
    breadcrumbs: [{ title: 'Leadership', href: index() }],
};
