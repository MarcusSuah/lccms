import { Head, Link, router } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { PageHeader } from '@/components/admin/page-header';
import { StatTile } from '@/components/admin/stat-tile';
import { NativeSelect } from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { population } from '@/routes/admin/census';
import type { BreakdownRow, CensusSummary, GeoLevel } from '@/types';

type CampaignOption = {
    id: number;
    name: string;
    reference_code: string;
    status: string;
};

const plural: Record<GeoLevel, string> = {
    county: 'Counties',
    district: 'Districts',
    clan: 'Clans',
    town: 'Towns',
    community: 'Communities',
    block: 'Blocks',
};

const sexLabels: Record<string, string> = {
    male: 'Male',
    female: 'Female',
    not_stated: 'Not stated',
};

export default function Population({
    campaigns,
    campaign,
    level,
    childLevel,
    parent,
    summary,
    rows,
}: {
    campaigns: CampaignOption[];
    campaign: {
        id: number;
        name: string;
        reference_code: string;
        reference_date: string;
    } | null;
    level: GeoLevel;
    childLevel: GeoLevel | null;
    parent: {
        id: number;
        name: string;
        trail: { id: number; name: string; level: GeoLevel }[];
    } | null;
    summary: CensusSummary | null;
    rows: BreakdownRow[];
}) {
    if (!campaign || !summary) {
        return (
            <div className="p-6">
                <Head title="Population" />
                <EmptyState title="No campaign covers your areas" />
            </div>
        );
    }

    const approvedTotal = summary.occupants.approved;

    return (
        <>
            <Head title="Population report" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow={`Population · reference date ${campaign.reference_date}`}
                    title={parent ? parent.name : 'All areas in your reach'}
                    description="Official figures are approved occupant records only. Areas never visited are marked “not enumerated”, which is different from a verified zero."
                    actions={
                        <NativeSelect
                            aria-label="Campaign"
                            className="w-72"
                            value={campaign.id}
                            onChange={(event) =>
                                router.get(
                                    population.url({
                                        query: { campaign: event.target.value },
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
                    }
                />

                <nav
                    aria-label="Drill-down"
                    className="flex flex-wrap items-center gap-1 text-sm"
                >
                    <Link
                        href={population({ query: { campaign: campaign.id } })}
                        className="text-muted-foreground hover:text-foreground hover:underline"
                    >
                        All counties
                    </Link>
                    {parent?.trail.map((crumb, position) => {
                        const next =
                            position < parent.trail.length - 1
                                ? parent.trail[position + 1].level
                                : level;

                        return (
                            <span
                                key={`${crumb.level}-${crumb.id}`}
                                className="flex items-center gap-1"
                            >
                                <ChevronRight
                                    className="size-3.5"
                                    aria-hidden
                                />
                                <Link
                                    href={population({
                                        query: {
                                            campaign: campaign.id,
                                            level: next,
                                            parent: crumb.id,
                                        },
                                    })}
                                    className="text-muted-foreground hover:text-foreground hover:underline"
                                >
                                    {crumb.name}
                                </Link>
                            </span>
                        );
                    })}
                </nav>

                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <StatTile
                        label="Approved population"
                        value={approvedTotal}
                    />
                    <StatTile
                        label="Approved households"
                        value={summary.households.approved}
                    />
                    <StatTile
                        label="Average household size"
                        value={summary.average_household_size}
                        emptyLabel="No approved households"
                    />
                    <StatTile
                        label="Awaiting approval"
                        value={
                            summary.occupants.submitted -
                            summary.occupants.approved
                        }
                        hint={`${summary.occupants.registered.toLocaleString()} registered in total`}
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="rounded-lg border p-4">
                        <h2 className="mb-2 text-sm font-medium">
                            Approved population by sex
                        </h2>
                        <table className="w-full text-sm">
                            <tbody>
                                {Object.entries(summary.sex).length === 0 && (
                                    <tr>
                                        <td className="text-muted-foreground">
                                            No approved records yet.
                                        </td>
                                    </tr>
                                )}
                                {Object.entries(summary.sex).map(
                                    ([sex, total]) => (
                                        <tr key={sex}>
                                            <td className="py-1">
                                                {sexLabels[sex] ?? sex}
                                            </td>
                                            <td className="py-1 text-right tabular-nums">
                                                {total.toLocaleString()}
                                            </td>
                                            <td className="py-1 text-right text-muted-foreground tabular-nums">
                                                {approvedTotal > 0
                                                    ? `${((total / approvedTotal) * 100).toFixed(1)}%`
                                                    : '—'}
                                            </td>
                                        </tr>
                                    ),
                                )}
                            </tbody>
                        </table>
                    </section>
                    <section className="rounded-lg border p-4">
                        <h2 className="mb-2 text-sm font-medium">
                            Approved population by age group
                        </h2>
                        <table className="w-full text-sm">
                            <tbody>
                                {Object.entries(summary.age_groups).map(
                                    ([group, total]) => (
                                        <tr key={group}>
                                            <td className="py-1">{group}</td>
                                            <td className="py-1 text-right tabular-nums">
                                                {total.toLocaleString()}
                                            </td>
                                            <td className="py-1 text-right text-muted-foreground tabular-nums">
                                                {approvedTotal > 0
                                                    ? `${((total / approvedTotal) * 100).toFixed(1)}%`
                                                    : '—'}
                                            </td>
                                        </tr>
                                    ),
                                )}
                            </tbody>
                        </table>
                    </section>
                </div>

                <section className="space-y-2">
                    <h2 className="text-sm font-medium">{plural[level]}</h2>
                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Area</TableHead>
                                    <TableHead className="text-right">
                                        Houses visited
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Households (approved)
                                    </TableHead>
                                    <TableHead className="text-right">
                                        People registered
                                    </TableHead>
                                    <TableHead className="text-right">
                                        People approved
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Avg. household
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell>
                                            {childLevel ? (
                                                <Link
                                                    href={population({
                                                        query: {
                                                            campaign:
                                                                campaign.id,
                                                            level: childLevel,
                                                            parent: row.id,
                                                        },
                                                    })}
                                                    className="font-medium hover:underline"
                                                >
                                                    {row.name}
                                                </Link>
                                            ) : (
                                                <span className="font-medium">
                                                    {row.name}
                                                </span>
                                            )}
                                            <div className="font-mono text-xs text-muted-foreground">
                                                {row.reference_code}
                                            </div>
                                        </TableCell>
                                        {row.is_enumerated ? (
                                            <>
                                                <TableCell className="text-right tabular-nums">
                                                    {row.houses_visited.toLocaleString()}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {row.households.approved.toLocaleString()}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {row.occupants.registered.toLocaleString()}
                                                </TableCell>
                                                <TableCell className="text-right font-medium tabular-nums">
                                                    {row.occupants.approved.toLocaleString()}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {row.average_household_size ??
                                                        '—'}
                                                </TableCell>
                                            </>
                                        ) : (
                                            <TableCell
                                                colSpan={5}
                                                className="text-right text-sm text-muted-foreground"
                                            >
                                                Not enumerated
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </section>
            </div>
        </>
    );
}

Population.layout = {
    breadcrumbs: [{ title: 'Population', href: population() }],
};
