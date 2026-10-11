import { Head, Link, router, useForm } from '@inertiajs/react';
import { Pencil, UserPlus, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { FormField } from '@/components/admin/form-field';
import { PageHeader } from '@/components/admin/page-header';
import { StatTile } from '@/components/admin/stat-tile';
import { StatusBadge } from '@/components/admin/status-badge';
import { GeoAreaPicker } from '@/components/geo/geo-area-picker';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import { population } from '@/routes/admin/census';
import {
    edit,
    index,
    status as statusRoute,
} from '@/routes/admin/census/campaigns';
import {
    candidates as candidatesRoute,
    destroy as destroyAssignment,
    store as storeAssignment,
} from '@/routes/admin/census/campaigns/assignments';
import { index as householdsIndex } from '@/routes/admin/census/households';
import type {
    CampaignSummary,
    CensusSummary,
    GeoLevel,
    Option,
    QualityCheck,
} from '@/types';

type Assignment = {
    id: number;
    user: { id: number; name: string; email: string } | null;
    role: string;
    supervisor: string | null;
    area: string;
    starts_on: string;
    ends_on: string;
    status: string;
    expected_houses: number | null;
    progress: { registered: number; approved: number; returned: number } | null;
};

function useCandidates(campaignId: number, role: string, open: boolean) {
    const [options, setOptions] = useState<Option<number>[]>([]);

    useEffect(() => {
        if (!open) {
            return;
        }

        let cancelled = false;

        void fetch(candidatesRoute.url(campaignId, { query: { role } }), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((response) => (response.ok ? response.json() : { data: [] }))
            .then((body: { data: Option<number>[] }) => {
                if (!cancelled) {
                    setOptions(body.data);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [campaignId, role, open]);

    return options;
}

function AssignDialog({
    campaign,
    roleOptions,
    replaces,
}: {
    campaign: CampaignSummary;
    roleOptions: Option[];
    replaces?: Assignment;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        user_id: 0,
        role: replaces?.role ?? 'enumerator',
        supervisor_id: '' as number | '',
        level: '' as GeoLevel | '',
        area_id: 0,
        starts_on: new Date().toISOString().slice(0, 10),
        ends_on: campaign.end_date,
        expected_houses: '',
        notes: '',
        replaces_id: replaces?.id ?? '',
    });
    const people = useCandidates(campaign.id, form.data.role, open);
    const supervisors = useCandidates(campaign.id, 'supervisor', open);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {replaces ? (
                    <Button variant="ghost" size="sm">
                        Reassign
                    </Button>
                ) : (
                    <Button>
                        <UserPlus /> Assign staff
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="sm:max-w-3xl">
                <form
                    className="space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.submit(storeAssignment(campaign.id), {
                            preserveScroll: true,
                            onSuccess: () => setOpen(false),
                        });
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>
                            {replaces
                                ? `Reassign ${replaces.area}`
                                : 'New assignment'}
                        </DialogTitle>
                    </DialogHeader>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label="Role"
                            htmlFor="role"
                            error={form.errors.role}
                        >
                            <NativeSelect
                                id="role"
                                value={form.data.role}
                                onChange={(event) =>
                                    form.setData('role', event.target.value)
                                }
                            >
                                {roleOptions.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                        <FormField
                            label="Staff member"
                            htmlFor="user_id"
                            error={form.errors.user_id}
                            required
                        >
                            <NativeSelect
                                id="user_id"
                                value={form.data.user_id || ''}
                                onChange={(event) =>
                                    form.setData(
                                        'user_id',
                                        Number(event.target.value),
                                    )
                                }
                            >
                                <option value="">Select…</option>
                                {people.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                        {form.data.role === 'enumerator' && (
                            <FormField
                                label="Supervisor"
                                htmlFor="supervisor_id"
                                error={form.errors.supervisor_id}
                            >
                                <NativeSelect
                                    id="supervisor_id"
                                    value={form.data.supervisor_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'supervisor_id',
                                            event.target.value === ''
                                                ? ''
                                                : Number(event.target.value),
                                        )
                                    }
                                >
                                    <option value="">None</option>
                                    {supervisors.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </FormField>
                        )}
                        <FormField
                            label="Expected houses"
                            htmlFor="expected_houses"
                            error={form.errors.expected_houses}
                        >
                            <Input
                                id="expected_houses"
                                type="number"
                                min={0}
                                value={form.data.expected_houses}
                                onChange={(event) =>
                                    form.setData(
                                        'expected_houses',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label="From"
                            htmlFor="starts_on"
                            error={form.errors.starts_on}
                        >
                            <Input
                                id="starts_on"
                                type="date"
                                value={form.data.starts_on}
                                onChange={(event) =>
                                    form.setData(
                                        'starts_on',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label="Until"
                            htmlFor="ends_on"
                            error={form.errors.ends_on}
                        >
                            <Input
                                id="ends_on"
                                type="date"
                                value={form.data.ends_on}
                                onChange={(event) =>
                                    form.setData('ends_on', event.target.value)
                                }
                            />
                        </FormField>
                    </div>
                    <GeoAreaPicker
                        maxLevel="block"
                        idPrefix="assignment"
                        onChange={(selection) => {
                            form.setData((data) => ({
                                ...data,
                                level: selection?.level ?? '',
                                area_id: selection?.id ?? 0,
                            }));
                        }}
                    />
                    {form.errors.area_id && (
                        <p className="text-sm text-destructive">
                            {form.errors.area_id}
                        </p>
                    )}
                    <DialogFooter>
                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                !form.data.user_id ||
                                !form.data.area_id
                            }
                        >
                            Save assignment
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

const qualityTone: Record<QualityCheck['severity'], string> = {
    error: 'text-red-700 dark:text-red-400',
    warning: 'text-amber-700 dark:text-amber-400',
    info: 'text-muted-foreground',
};

export default function CampaignShow({
    campaign,
    summary,
    quality,
    assignments,
    roleOptions,
    can,
}: {
    campaign: CampaignSummary & {
        description: string | null;
        instructions: string | null;
        scopes: { id: number; label: string }[];
        allowed_transitions: Option[];
    };
    summary: CensusSummary;
    quality: QualityCheck[];
    assignments: Assignment[];
    roleOptions: Option[];
    can: { manage: boolean; assign: boolean };
}) {
    const changeStatus = (status: string) => {
        const warning =
            status === 'closed'
                ? 'Closing freezes approved results permanently and ends all assignments. Unapproved records will never count. Continue?'
                : `Change the campaign status to “${status}”?`;

        if (window.confirm(warning)) {
            router.patch(statusRoute.url(campaign.id), { status });
        }
    };

    return (
        <>
            <Head title={campaign.name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow={`Campaign · ${campaign.reference_code}`}
                    title={campaign.name}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            <StatusBadge status={campaign.status} />
                            Field work {campaign.start_date} →{' '}
                            {campaign.end_date} · reference date{' '}
                            {campaign.reference_date}
                        </span>
                    }
                    actions={
                        <>
                            <Button asChild variant="outline">
                                <Link
                                    href={householdsIndex({
                                        query: { campaign: campaign.id },
                                    })}
                                >
                                    Review households
                                </Link>
                            </Button>
                            <Button asChild variant="outline">
                                <Link
                                    href={population({
                                        query: { campaign: campaign.id },
                                    })}
                                >
                                    Population report
                                </Link>
                            </Button>
                            {can.manage && (
                                <Button asChild variant="outline">
                                    <Link href={edit(campaign.id)}>
                                        <Pencil /> Edit
                                    </Link>
                                </Button>
                            )}
                            {can.manage &&
                                campaign.allowed_transitions.map(
                                    (transition) => (
                                        <Button
                                            key={transition.value}
                                            variant={
                                                transition.value === 'closed'
                                                    ? 'destructive'
                                                    : 'secondary'
                                            }
                                            onClick={() =>
                                                changeStatus(transition.value)
                                            }
                                        >
                                            Move to {transition.label}
                                        </Button>
                                    ),
                                )}
                        </>
                    }
                />

                <section aria-label="Population figures" className="space-y-2">
                    <h2 className="text-sm font-medium text-muted-foreground">
                        Occupants (within your areas)
                    </h2>
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <StatTile
                            label="Registered"
                            value={summary.occupants.registered}
                        />
                        <StatTile
                            label="Submitted"
                            value={summary.occupants.submitted}
                        />
                        <StatTile
                            label="Verified"
                            value={summary.occupants.verified}
                        />
                        <StatTile
                            label="Approved (official)"
                            value={summary.occupants.approved}
                            hint="Only approved records enter official totals."
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <StatTile
                            label="Houses visited"
                            value={summary.houses.visited}
                            hint={`${summary.houses.vacant} vacant · ${summary.houses.not_enumerated} refused or not contacted`}
                        />
                        <StatTile
                            label="Households registered"
                            value={summary.households.registered}
                        />
                        <StatTile
                            label="Households approved"
                            value={summary.households.approved}
                        />
                        <StatTile
                            label="Average household size"
                            value={summary.average_household_size}
                            emptyLabel="No approved households"
                        />
                    </div>
                </section>

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Data quality</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="space-y-2 text-sm">
                                {quality.map((check) => (
                                    <li
                                        key={check.key}
                                        className="flex items-center justify-between gap-2"
                                    >
                                        <span
                                            className={cn(
                                                check.count > 0 &&
                                                    qualityTone[check.severity],
                                            )}
                                        >
                                            {check.label}
                                        </span>
                                        <span className="font-medium tabular-nums">
                                            {check.count}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Coverage</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {campaign.scopes.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No coverage defined yet.
                                </p>
                            ) : (
                                <ul className="space-y-1 text-sm">
                                    {campaign.scopes.map((scope) => (
                                        <li key={scope.id}>{scope.label}</li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Instructions</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm whitespace-pre-line text-muted-foreground">
                            {campaign.instructions ||
                                'No enumeration instructions recorded.'}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="flex-row items-center justify-between">
                        <CardTitle>Assignments</CardTitle>
                        {can.assign && (
                            <AssignDialog
                                campaign={campaign}
                                roleOptions={roleOptions}
                            />
                        )}
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        {assignments.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No staff assigned yet.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Staff</TableHead>
                                        <TableHead>Role</TableHead>
                                        <TableHead>Area</TableHead>
                                        <TableHead>Period</TableHead>
                                        <TableHead className="text-right">
                                            Households (approved / returned)
                                        </TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="sr-only">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {assignments.map((assignment) => (
                                        <TableRow key={assignment.id}>
                                            <TableCell>
                                                <div className="font-medium">
                                                    {assignment.user?.name}
                                                </div>
                                                {assignment.supervisor && (
                                                    <div className="text-xs text-muted-foreground">
                                                        Supervisor:{' '}
                                                        {assignment.supervisor}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell className="capitalize">
                                                {assignment.role}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {assignment.area}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {assignment.starts_on} →{' '}
                                                {assignment.ends_on}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {assignment.progress
                                                    ? `${assignment.progress.registered}${assignment.expected_houses ? ` / ${assignment.expected_houses} expected` : ''} (${assignment.progress.approved} / ${assignment.progress.returned})`
                                                    : '—'}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    status={assignment.status}
                                                />
                                            </TableCell>
                                            <TableCell className="text-right whitespace-nowrap">
                                                {can.assign &&
                                                    assignment.status ===
                                                        'active' && (
                                                        <>
                                                            <AssignDialog
                                                                campaign={
                                                                    campaign
                                                                }
                                                                roleOptions={
                                                                    roleOptions
                                                                }
                                                                replaces={
                                                                    assignment
                                                                }
                                                            />
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label="Revoke assignment"
                                                                onClick={() => {
                                                                    if (
                                                                        window.confirm(
                                                                            'Revoke this assignment? Records already collected are kept.',
                                                                        )
                                                                    ) {
                                                                        router.delete(
                                                                            destroyAssignment.url(
                                                                                {
                                                                                    campaign:
                                                                                        campaign.id,
                                                                                    assignment:
                                                                                        assignment.id,
                                                                                },
                                                                            ),
                                                                            {
                                                                                preserveScroll: true,
                                                                            },
                                                                        );
                                                                    }
                                                                }}
                                                            >
                                                                <X />
                                                            </Button>
                                                        </>
                                                    )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

CampaignShow.layout = {
    breadcrumbs: [{ title: 'Campaigns', href: index() }],
};
