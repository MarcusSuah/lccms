import { Head, Link } from '@inertiajs/react';
import { FileUp, MapPinned, Plus } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { FilterBar } from '@/components/admin/filter-bar';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { StatusBadge } from '@/components/admin/status-badge';
import { GeoBreadcrumb } from '@/components/geo/geo-breadcrumb';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import { create, index, show } from '@/routes/admin/geography';
import { index as importsIndex } from '@/routes/admin/geography/imports';
import type { GeoArea, GeoLevel, Option, Paginated } from '@/types';

const plural: Record<GeoLevel, string> = {
    county: 'Counties',
    district: 'Districts',
    clan: 'Clans',
    town: 'Towns',
    community: 'Communities',
    block: 'Blocks',
};

export default function GeographyIndex({
    level,
    levels,
    areas,
    parent,
    filters,
    statusOptions,
    verificationOptions,
    can,
}: {
    level: GeoLevel;
    levels: Option<GeoLevel>[];
    areas: Paginated<GeoArea>;
    parent: GeoArea | null;
    filters: {
        search: string;
        parent_id: number | null;
        status: string;
        verification_status: string;
    };
    statusOptions: Option[];
    verificationOptions: Option[];
    can: { create: boolean; import: boolean };
}) {
    return (
        <>
            <Head title={plural[level]} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Geographic registry"
                    title={
                        parent
                            ? `${plural[level]} in ${parent.name}`
                            : plural[level]
                    }
                    description="County → District → Clan → Town → Community → Block. Moving an area to a different parent uses the audited reassignment workflow."
                    actions={
                        <>
                            {can.import && (
                                <Button asChild variant="outline">
                                    <Link href={importsIndex()}>
                                        <FileUp /> Import
                                    </Link>
                                </Button>
                            )}
                            {can.create && (
                                <Button asChild>
                                    <Link
                                        href={create(level, {
                                            query: parent
                                                ? { parent_id: parent.id }
                                                : {},
                                        })}
                                    >
                                        <Plus /> New {level}
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                <nav
                    aria-label="Geographic levels"
                    className="flex flex-wrap gap-1 rounded-lg bg-muted p-1"
                >
                    {levels.map((option) => (
                        <Link
                            key={option.value}
                            href={index(option.value)}
                            className={cn(
                                'rounded-md px-3 py-1.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground',
                                option.value === level &&
                                    'bg-background text-foreground shadow-sm',
                            )}
                        >
                            {plural[option.value]}
                        </Link>
                    ))}
                </nav>

                {parent?.breadcrumb && (
                    <GeoBreadcrumb trail={parent.breadcrumb} linkLast />
                )}

                <FilterBar
                    filters={filters}
                    searchPlaceholder="Name or reference code"
                    selects={[
                        {
                            name: 'status',
                            label: 'Status',
                            options: statusOptions,
                        },
                        {
                            name: 'verification_status',
                            label: 'Verification',
                            options: verificationOptions,
                        },
                    ]}
                />

                {areas.data.length === 0 ? (
                    <EmptyState
                        icon={MapPinned}
                        title={`No ${plural[level].toLowerCase()} found`}
                        description="Official records arrive through verified imports. Records marked DEV- are development fixtures."
                    />
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Reference</TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        Located in
                                    </TableHead>
                                    {level !== 'block' && (
                                        <TableHead className="text-right">
                                            Children
                                        </TableHead>
                                    )}
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {areas.data.map((area) => (
                                    <TableRow key={area.id}>
                                        <TableCell className="font-medium">
                                            <Link
                                                href={show({
                                                    level,
                                                    geoArea: area.id,
                                                })}
                                                className="hover:underline"
                                            >
                                                {area.name}
                                            </Link>
                                            {area.is_fixture && (
                                                <span className="ml-2 text-xs text-amber-600">
                                                    fixture
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {area.reference_code}
                                        </TableCell>
                                        <TableCell className="hidden text-sm text-muted-foreground md:table-cell">
                                            {area.breadcrumb
                                                ?.slice(0, -1)
                                                .map((crumb) => crumb.name)
                                                .join(' › ') || '—'}
                                        </TableCell>
                                        {level !== 'block' && (
                                            <TableCell className="text-right tabular-nums">
                                                {area.children_count ?? 0}
                                            </TableCell>
                                        )}
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                <StatusBadge
                                                    status={area.status}
                                                />
                                                <StatusBadge
                                                    status={
                                                        area.verification_status
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
                    links={areas.meta.links}
                    from={areas.meta.from}
                    to={areas.meta.to}
                    total={areas.meta.total}
                />
            </div>
        </>
    );
}

GeographyIndex.layout = {
    breadcrumbs: [{ title: 'Geography', href: index('county') }],
};
