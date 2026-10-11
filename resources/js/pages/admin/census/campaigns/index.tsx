import { Head, Link } from '@inertiajs/react';
import { ClipboardList, Plus } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
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
import { create, index, show } from '@/routes/admin/census/campaigns';
import type { CampaignSummary, SimplePaginated } from '@/types';

export default function CampaignsIndex({
    campaigns,
    can,
}: {
    campaigns: SimplePaginated<CampaignSummary>;
    can: { create: boolean };
}) {
    return (
        <>
            <Head title="Census campaigns" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Census"
                    title="Campaigns"
                    description="Each campaign keeps its own records. Closing a campaign freezes its approved results; a new campaign never overwrites an earlier one."
                    actions={
                        can.create && (
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus /> New campaign
                                </Link>
                            </Button>
                        )
                    }
                />
                {campaigns.data.length === 0 ? (
                    <EmptyState
                        icon={ClipboardList}
                        title="No campaigns yet"
                        description="Campaigns covering your areas appear here."
                    />
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Campaign</TableHead>
                                    <TableHead>Field period</TableHead>
                                    <TableHead>Reference date</TableHead>
                                    <TableHead className="text-right">
                                        Households
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {campaigns.data.map((campaign) => (
                                    <TableRow key={campaign.id}>
                                        <TableCell>
                                            <Link
                                                href={show(campaign.id)}
                                                className="font-medium hover:underline"
                                            >
                                                {campaign.name}
                                            </Link>
                                            <div className="font-mono text-xs text-muted-foreground">
                                                {campaign.reference_code}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {campaign.start_date} →{' '}
                                            {campaign.end_date}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {campaign.reference_date}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {campaign.households_count?.toLocaleString()}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                status={campaign.status}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
                <Pagination
                    links={campaigns.links}
                    from={campaigns.from}
                    to={campaigns.to}
                    total={campaigns.total}
                />
            </div>
        </>
    );
}

CampaignsIndex.layout = {
    breadcrumbs: [{ title: 'Campaigns', href: index() }],
};
