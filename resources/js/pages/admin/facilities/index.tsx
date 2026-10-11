import { Head, Link } from '@inertiajs/react';
import { Building2, ClipboardCheck, Plus } from 'lucide-react';
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
import { create, index, show } from '@/routes/admin/facilities';
import { index as needsIndex } from '@/routes/admin/needs-assessment';
import type { Option, SimplePaginated } from '@/types';

type FacilityRow = {
    id: number;
    reference_code: string;
    name: string;
    type: string | null;
    category_label: string;
    ownership: string;
    operating_status: string;
    verification_status: string;
    location: string;
};

export default function FacilitiesIndex({
    facilities,
    filters,
    options,
    can,
}: {
    facilities: SimplePaginated<FacilityRow>;
    filters: Record<string, string>;
    options: {
        categories: Option[];
        ownership: Option[];
        operatingStatus: Option[];
        verification: Option[];
    };
    can: { create: boolean };
}) {
    return (
        <>
            <Head title="Facilities" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Community services"
                    title="Facilities"
                    description="Schools, health facilities, markets, public toilets and other services. Each facility has one category and one ownership, so it is counted once."
                    actions={
                        <>
                            <Button asChild variant="outline">
                                <Link href={needsIndex()}>
                                    <ClipboardCheck /> Needs assessment
                                </Link>
                            </Button>
                            {can.create && (
                                <Button asChild>
                                    <Link href={create()}>
                                        <Plus /> New facility
                                    </Link>
                                </Button>
                            )}
                        </>
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
                            name: 'ownership',
                            label: 'Ownership',
                            options: options.ownership,
                        },
                        {
                            name: 'operating_status',
                            label: 'Operating',
                            options: options.operatingStatus,
                        },
                        {
                            name: 'verification_status',
                            label: 'Verification',
                            options: options.verification,
                        },
                    ]}
                />
                {facilities.data.length === 0 ? (
                    <EmptyState
                        icon={Building2}
                        title="No facilities recorded"
                        description="An empty register is not the same as no facilities. Use the needs assessment to record a verified absence."
                    />
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Facility</TableHead>
                                    <TableHead>Category</TableHead>
                                    <TableHead>Ownership</TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        Location
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {facilities.data.map((facility) => (
                                    <TableRow key={facility.id}>
                                        <TableCell>
                                            <Link
                                                href={show(facility.id)}
                                                className="font-medium hover:underline"
                                            >
                                                {facility.name}
                                            </Link>
                                            <div className="font-mono text-xs text-muted-foreground">
                                                {facility.reference_code}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {facility.category_label}
                                            <div className="text-xs text-muted-foreground">
                                                {facility.type}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {facility.ownership}
                                        </TableCell>
                                        <TableCell className="hidden text-sm md:table-cell">
                                            {facility.location}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                <StatusBadge
                                                    status={
                                                        facility.operating_status
                                                    }
                                                />
                                                <StatusBadge
                                                    status={
                                                        facility.verification_status
                                                    }
                                                />
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
                <Pagination
                    links={facilities.links}
                    from={facilities.from}
                    to={facilities.to}
                    total={facilities.total}
                />
            </div>
        </>
    );
}

FacilitiesIndex.layout = {
    breadcrumbs: [{ title: 'Facilities', href: index() }],
};
