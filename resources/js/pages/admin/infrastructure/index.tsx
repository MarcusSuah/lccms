import { Head, Link } from '@inertiajs/react';
import { Construction, Plus } from 'lucide-react';
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
import { create, edit, index } from '@/routes/admin/infrastructure';
import type { Option, SimplePaginated } from '@/types';

type Asset = {
    id: number;
    reference_code: string;
    name: string;
    category_label: string;
    condition: string;
    availability: string;
    verification_status: string;
    last_inspected_on: string | null;
    location: string;
};

export default function InfrastructureIndex({
    assets,
    filters,
    options,
    can,
}: {
    assets: SimplePaginated<Asset>;
    filters: Record<string, string>;
    options: { categories: Option[]; conditions: Option[] };
    can: { manage: boolean };
}) {
    return (
        <>
            <Head title="Infrastructure" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Community development"
                    title="Infrastructure"
                    description="Roads, bridges, water, electricity, sanitation, waste, public buildings and connectivity, with condition and maintenance needs."
                    actions={
                        can.manage && (
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus /> New asset
                                </Link>
                            </Button>
                        )
                    }
                />
                <FilterBar
                    filters={filters}
                    searchPlaceholder="Name or reference"
                    selects={[
                        {
                            name: 'category',
                            label: 'Category',
                            options: options.categories,
                        },
                        {
                            name: 'condition',
                            label: 'Condition',
                            options: options.conditions,
                        },
                    ]}
                />
                {assets.data.length === 0 ? (
                    <EmptyState
                        icon={Construction}
                        title="No infrastructure recorded"
                    />
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Asset</TableHead>
                                    <TableHead>Category</TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        Location
                                    </TableHead>
                                    <TableHead>Condition</TableHead>
                                    <TableHead className="sr-only">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {assets.data.map((asset) => (
                                    <TableRow key={asset.id}>
                                        <TableCell>
                                            <div className="font-medium">
                                                {asset.name}
                                            </div>
                                            <div className="font-mono text-xs text-muted-foreground">
                                                {asset.reference_code}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {asset.category_label}
                                        </TableCell>
                                        <TableCell className="hidden text-sm md:table-cell">
                                            {asset.location}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                <StatusBadge
                                                    status={asset.condition}
                                                />
                                                <StatusBadge
                                                    status={
                                                        asset.verification_status
                                                    }
                                                />
                                            </div>
                                            {asset.last_inspected_on && (
                                                <div className="text-xs text-muted-foreground">
                                                    Inspected{' '}
                                                    {asset.last_inspected_on}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {can.manage && (
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="ghost"
                                                >
                                                    <Link href={edit(asset.id)}>
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
                    links={assets.links}
                    from={assets.from}
                    to={assets.to}
                    total={assets.total}
                />
            </div>
        </>
    );
}

InfrastructureIndex.layout = {
    breadcrumbs: [{ title: 'Infrastructure', href: index() }],
};
