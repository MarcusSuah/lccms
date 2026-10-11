import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Baby,
    GitMerge,
    Home,
    ListChecks,
    MessageSquareWarning,
    UserCheck,
    Users,
} from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { PageHeader } from '@/components/admin/page-header';
import { StatTile } from '@/components/admin/stat-tile';
import { StatusBadge } from '@/components/admin/status-badge';
import { NativeSelect } from '@/components/ui/native-select';
import { dashboard } from '@/routes';
import { index as householdsIndex } from '@/routes/admin/census/households';
import { index as complaintsIndex } from '@/routes/admin/complaints';
import { index as conflictsIndex } from '@/routes/admin/sync/conflicts';
import type { CensusSummary } from '@/types';

type CampaignOption = {
    id: number;
    name: string;
    reference_code: string;
    status: string;
};

export default function Dashboard({
    campaigns,
    campaign,
    summary,
    workload,
    complaints,
}: {
    campaigns: CampaignOption[];
    campaign: {
        id: number;
        name: string;
        status: string;
        reference_date: string;
    } | null;
    summary: CensusSummary | null;
    workload: {
        awaiting_review: number;
        open_conflicts: number | null;
    } | null;
    complaints: {
        by_status: { status: string; label: string; total: number }[];
        open: number;
        urgent_open: number;
    } | null;
}) {
    const male = summary?.sex.male ?? 0;
    const female = summary?.sex.female ?? 0;
    const notStated = summary?.sex.not_stated ?? 0;

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Dashboard"
                    description={
                        campaign ? (
                            <span className="flex flex-wrap items-center gap-2">
                                <StatusBadge status={campaign.status} />
                                Approved records within your areas · ages as of{' '}
                                {campaign.reference_date}
                            </span>
                        ) : undefined
                    }
                    actions={
                        campaigns.length > 1 && (
                            <NativeSelect
                                aria-label="Campaign"
                                className="w-72"
                                value={campaign?.id ?? ''}
                                onChange={(event) =>
                                    router.get(
                                        dashboard.url({
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
                        )
                    }
                />

                {complaints && (
                    <section
                        aria-labelledby="complaints-heading"
                        className="space-y-3"
                    >
                        <div className="flex flex-wrap items-baseline justify-between gap-2">
                            <h2 id="complaints-heading" className="font-medium">
                                Complaints in your areas
                            </h2>
                            <Link
                                href={complaintsIndex()}
                                className="text-sm text-primary hover:underline"
                            >
                                View all
                            </Link>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <StatTile
                                icon={MessageSquareWarning}
                                label="Open complaints"
                                value={complaints.open}
                            />
                            <StatTile
                                icon={AlertTriangle}
                                label="Urgent and open"
                                value={complaints.urgent_open}
                            />
                        </div>
                        <ul
                            className="flex flex-wrap gap-2"
                            aria-label="Complaints by status"
                        >
                            {complaints.by_status
                                .filter((item) => item.total > 0)
                                .map((item) => (
                                    <li key={item.status}>
                                        <Link
                                            href={complaintsIndex({
                                                query: { status: item.status },
                                            })}
                                            className="flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm hover:bg-accent"
                                        >
                                            <StatusBadge
                                                status={item.status}
                                                label={item.label}
                                            />
                                            <span className="font-medium tabular-nums">
                                                {item.total.toLocaleString()}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                        </ul>
                    </section>
                )}

                {!summary ? (
                    !complaints && (
                        <EmptyState
                            title="No census figures to show"
                            description="Figures appear here once a campaign covering your areas has approved records."
                        />
                    )
                ) : (
                    <>
                        <section
                            aria-label="Population"
                            className="grid gap-3 sm:grid-cols-2"
                        >
                            <StatTile
                                icon={Users}
                                label="Total occupants"
                                value={summary.occupants.approved}
                                hint={`${summary.occupants.registered.toLocaleString()} registered, ${(summary.occupants.submitted - summary.occupants.approved).toLocaleString()} awaiting approval`}
                            />
                            <StatTile
                                icon={UserCheck}
                                label="Adults (18+)"
                                value={summary.adults}
                            />
                            <StatTile
                                icon={Baby}
                                label="Children (under 18)"
                                value={summary.children}
                                hint={
                                    summary.age_unknown > 0
                                        ? `${summary.age_unknown.toLocaleString()} with unknown age`
                                        : undefined
                                }
                            />
                            <StatTile
                                icon={Users}
                                label="Male / Female"
                                value={`${male.toLocaleString()} / ${female.toLocaleString()}`}
                                hint={
                                    notStated > 0
                                        ? `${notStated.toLocaleString()} not stated`
                                        : undefined
                                }
                            />
                        </section>

                        <section
                            aria-label="Housing and workload"
                            className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                        >
                            <StatTile
                                icon={Home}
                                label="Households (approved)"
                                value={summary.households.approved}
                            />
                            <StatTile
                                label="Average household size"
                                value={summary.average_household_size}
                                emptyLabel="No approved households"
                            />
                            {workload && (
                                <Link
                                    href={householdsIndex({
                                        query: {
                                            campaign: campaign?.id,
                                            status: 'submitted',
                                        },
                                    })}
                                    className="rounded-lg focus-visible:ring-2 focus-visible:outline-none"
                                >
                                    <StatTile
                                        icon={ListChecks}
                                        label="Households awaiting review"
                                        value={workload.awaiting_review}
                                        className="h-full hover:bg-accent"
                                    />
                                </Link>
                            )}
                            {workload?.open_conflicts !== null &&
                                workload?.open_conflicts !== undefined && (
                                    <Link
                                        href={conflictsIndex()}
                                        className="rounded-lg focus-visible:ring-2 focus-visible:outline-none"
                                    >
                                        <StatTile
                                            icon={GitMerge}
                                            label="Open sync conflicts"
                                            value={workload.open_conflicts}
                                            className="h-full hover:bg-accent"
                                        />
                                    </Link>
                                )}
                        </section>
                    </>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
