import { Head, Link, router } from '@inertiajs/react';
import { Home } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { FilterBar } from '@/components/admin/filter-bar';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { StatusBadge } from '@/components/admin/status-badge';
import { NativeSelect } from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index, show } from '@/routes/admin/census/households';
import type { Option, SimplePaginated } from '@/types';

type CampaignOption = {
    id: number;
    name: string;
    reference_code: string;
    status: string;
};

type HouseholdRow = {
    id: number;
    reference_code: string;
    status: string;
    occupants_count: number;
    house: string | null;
    area: string;
    enumerator: string | null;
    contact_name: string | null;
    updated_at: string | null;
};

export default function HouseholdsIndex({
    campaigns,
    campaign,
    households,
    filters,
    statusOptions,
}: {
    campaigns: CampaignOption[];
    campaign: CampaignOption | null;
    households: SimplePaginated<HouseholdRow> | null;
    filters: { campaign: number | null; status: string; search: string };
    statusOptions: Option[];
}) {
    return (
        <>
            <Head title="Household review" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Census"
                    title="Households"
                    description="Submitted households wait for supervisor verification, then administrator approval. Returned households go back to their enumerator."
                />

                {campaigns.length === 0 || !households ? (
                    <EmptyState
                        icon={Home}
                        title="No campaign covers your areas"
                    />
                ) : (
                    <>
                        <div className="flex flex-col gap-2 lg:flex-row lg:items-center">
                            <NativeSelect
                                aria-label="Campaign"
                                className="lg:w-72"
                                value={campaign?.id ?? ''}
                                onChange={(event) =>
                                    router.get(
                                        index.url({
                                            query: {
                                                campaign: event.target.value,
                                            },
                                        }),
                                    )
                                }
                            >
                                {campaigns.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.name} ({option.reference_code})
                                    </option>
                                ))}
                            </NativeSelect>
                            <FilterBar
                                filters={filters}
                                searchPlaceholder="Household reference"
                                selects={[
                                    {
                                        name: 'status',
                                        label: 'Status',
                                        options: statusOptions,
                                    },
                                ]}
                            />
                        </div>

                        {households.data.length === 0 ? (
                            <EmptyState title="No households match these filters" />
                        ) : (
                            <div className="overflow-x-auto rounded-lg border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Household</TableHead>
                                            <TableHead>Location</TableHead>
                                            <TableHead className="text-right">
                                                People
                                            </TableHead>
                                            <TableHead>Enumerator</TableHead>
                                            <TableHead>Status</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {households.data.map((row) => (
                                            <TableRow key={row.id}>
                                                <TableCell>
                                                    <Link
                                                        href={show(row.id)}
                                                        className="font-mono text-xs font-medium hover:underline"
                                                    >
                                                        {row.reference_code}
                                                    </Link>
                                                    {row.contact_name && (
                                                        <div className="text-xs text-muted-foreground">
                                                            {row.contact_name}
                                                        </div>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-sm">
                                                    {row.area}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {row.occupants_count}
                                                </TableCell>
                                                <TableCell className="text-sm">
                                                    {row.enumerator}
                                                </TableCell>
                                                <TableCell>
                                                    <StatusBadge
                                                        status={row.status}
                                                    />
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                        <Pagination
                            links={households.links}
                            from={households.from}
                            to={households.to}
                            total={households.total}
                        />
                    </>
                )}
            </div>
        </>
    );
}

HouseholdsIndex.layout = {
    breadcrumbs: [{ title: 'Households', href: index() }],
};
