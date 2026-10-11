import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowRightLeft, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import { FormField } from '@/components/admin/form-field';
import { MediaManager } from '@/components/admin/media-manager';
import type { OwnerMedia } from '@/components/admin/media-manager';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { StatusBadge } from '@/components/admin/status-badge';
import { GeoAreaPicker } from '@/components/geo/geo-area-picker';
import { GeoBreadcrumb } from '@/components/geo/geo-breadcrumb';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { create, edit, index, show } from '@/routes/admin/geography';
import { store as storeReassignment } from '@/routes/admin/geography/reassignments';
import type { GeoArea, GeoLevel, SimplePaginated } from '@/types';
import { GEO_LEVELS } from '@/types/models';

type ChildRow = {
    id: number;
    name: string;
    reference_code: string;
    status: string;
    verification_status: string;
};

type Reassignment = {
    id: number;
    status: string;
    reason: string;
    effective_on: string;
    requested_by: string | null;
    decided_by: string | null;
    decision_note: string | null;
    created_at: string | null;
};

type ActivityRow = {
    id: number;
    event: string | null;
    by: string | null;
    at: string | null;
    changes: string[];
};

function Detail({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="grid gap-0.5">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-sm">{value ?? '—'}</dd>
        </div>
    );
}

function ReassignDialog({ level, area }: { level: GeoLevel; area: GeoArea }) {
    const [open, setOpen] = useState(false);
    const parentLevel = GEO_LEVELS[GEO_LEVELS.indexOf(level) - 1];
    const form = useForm({
        to_parent_id: 0,
        reason: '',
        effective_on: new Date().toISOString().slice(0, 10),
    });

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <ArrowRightLeft /> Request move
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-2xl">
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.submit(
                            storeReassignment({ level, geoArea: area.id }),
                            {
                                preserveScroll: true,
                                onSuccess: () => setOpen(false),
                            },
                        );
                    }}
                    className="space-y-4"
                >
                    <DialogHeader>
                        <DialogTitle>Move {area.name}</DialogTitle>
                        <DialogDescription>
                            Choose the new {parentLevel}. A second authorised
                            person must approve before the move is applied.
                            Results of closed census campaigns keep their
                            original location.
                        </DialogDescription>
                    </DialogHeader>
                    <GeoAreaPicker
                        maxLevel={parentLevel}
                        exactLevel
                        idPrefix="move"
                        onChange={(selection) =>
                            form.setData('to_parent_id', selection?.id ?? 0)
                        }
                    />
                    {form.errors.to_parent_id && (
                        <p className="text-sm text-destructive">
                            {form.errors.to_parent_id}
                        </p>
                    )}
                    <FormField
                        label="Reason"
                        htmlFor="reason"
                        error={form.errors.reason}
                        required
                    >
                        <Textarea
                            id="reason"
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                            placeholder="Cite the decision or evidence for this change"
                        />
                    </FormField>
                    <FormField
                        label="Effective date"
                        htmlFor="effective_on"
                        error={form.errors.effective_on}
                        required
                    >
                        <Input
                            id="effective_on"
                            type="date"
                            value={form.data.effective_on}
                            onChange={(event) =>
                                form.setData('effective_on', event.target.value)
                            }
                        />
                    </FormField>
                    <DialogFooter>
                        <Button
                            type="submit"
                            disabled={
                                form.processing || form.data.to_parent_id === 0
                            }
                        >
                            Submit request
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function GeographyShow({
    level,
    area,
    childLevel,
    childAreas: children,
    reassignments,
    activity,
    media,
    can,
}: {
    level: GeoLevel;
    area: GeoArea;
    childLevel: GeoLevel | null;
    childAreas: SimplePaginated<ChildRow> | null;
    reassignments: Reassignment[];
    activity: ActivityRow[];
    media: OwnerMedia[] | null;
    can: {
        update: boolean;
        reassign: boolean;
        createChild: boolean;
        manageMedia: boolean;
    };
}) {
    return (
        <>
            <Head title={area.name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                {area.breadcrumb && <GeoBreadcrumb trail={area.breadcrumb} />}
                <PageHeader
                    eyebrow={`${level} · ${area.reference_code}`}
                    title={area.name}
                    description={area.description ?? undefined}
                    actions={
                        <>
                            {can.reassign && (
                                <ReassignDialog level={level} area={area} />
                            )}
                            {can.update && (
                                <Button asChild variant="outline">
                                    <Link
                                        href={edit({ level, geoArea: area.id })}
                                    >
                                        <Pencil /> Edit
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-1">
                        <CardHeader>
                            <CardTitle>Details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid grid-cols-2 gap-4">
                                <Detail
                                    label="Status"
                                    value={<StatusBadge status={area.status} />}
                                />
                                <Detail
                                    label="Verification"
                                    value={
                                        <StatusBadge
                                            status={area.verification_status}
                                        />
                                    }
                                />
                                {level === 'district' && (
                                    <Detail
                                        label="District number"
                                        value={
                                            area.district_number
                                                ? `#${area.district_number}`
                                                : null
                                        }
                                    />
                                )}
                                <Detail
                                    label="Coordinates"
                                    value={
                                        area.latitude && area.longitude
                                            ? `${area.latitude}, ${area.longitude}`
                                            : 'Not recorded'
                                    }
                                />
                                <Detail
                                    label="Boundary"
                                    value={
                                        area.has_boundary
                                            ? 'Recorded'
                                            : 'Not recorded'
                                    }
                                />
                                {level === 'community' && (
                                    <Detail
                                        label="Public profile"
                                        value={
                                            area.is_published
                                                ? 'Published'
                                                : 'Not published'
                                        }
                                    />
                                )}
                                {area.is_fixture && (
                                    <Detail
                                        label="Data origin"
                                        value="Development fixture"
                                    />
                                )}
                            </dl>
                        </CardContent>
                    </Card>

                    {children && childLevel && (
                        <Card className="lg:col-span-2">
                            <CardHeader className="flex-row items-center justify-between">
                                <CardTitle className="capitalize">
                                    {childLevel}s ({children.total})
                                </CardTitle>
                                {can.createChild && (
                                    <Button asChild size="sm">
                                        <Link
                                            href={create(childLevel, {
                                                query: { parent_id: area.id },
                                            })}
                                        >
                                            <Plus /> Add {childLevel}
                                        </Link>
                                    </Button>
                                )}
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {children.data.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No {childLevel}s registered yet.
                                    </p>
                                ) : (
                                    <ul className="divide-y rounded-md border">
                                        {children.data.map((child) => (
                                            <li
                                                key={child.id}
                                                className="flex items-center justify-between gap-2 px-3 py-2"
                                            >
                                                <Link
                                                    href={show({
                                                        level: childLevel,
                                                        geoArea: child.id,
                                                    })}
                                                    className="font-medium hover:underline"
                                                >
                                                    {child.name}
                                                </Link>
                                                <div className="flex items-center gap-2">
                                                    <span className="font-mono text-xs text-muted-foreground">
                                                        {child.reference_code}
                                                    </span>
                                                    <StatusBadge
                                                        status={
                                                            child.verification_status
                                                        }
                                                    />
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                <Pagination
                                    links={children.links}
                                    from={children.from}
                                    to={children.to}
                                    total={children.total}
                                />
                            </CardContent>
                        </Card>
                    )}
                </div>

                {level === 'community' && media && (
                    <MediaManager
                        ownerType="community"
                        ownerId={area.id}
                        media={media}
                        canManage={can.manageMedia}
                    />
                )}

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Reassignment history</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {reassignments.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    This area has never been moved.
                                </p>
                            ) : (
                                <ul className="space-y-3">
                                    {reassignments.map((item) => (
                                        <li key={item.id} className="text-sm">
                                            <div className="flex items-center gap-2">
                                                <StatusBadge
                                                    status={item.status}
                                                />
                                                <span className="text-muted-foreground">
                                                    effective{' '}
                                                    {item.effective_on}
                                                </span>
                                            </div>
                                            <p className="mt-1">
                                                {item.reason}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Requested by {item.requested_by}
                                                {item.decided_by &&
                                                    ` · decided by ${item.decided_by}`}
                                                {item.decision_note &&
                                                    ` · “${item.decision_note}”`}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Audit trail</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {activity.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No recorded activity.
                                </p>
                            ) : (
                                <ul className="space-y-2 text-sm">
                                    {activity.map((entry) => (
                                        <li key={entry.id}>
                                            <span className="font-medium capitalize">
                                                {entry.event}
                                            </span>{' '}
                                            by {entry.by ?? 'system'}
                                            {entry.at &&
                                                ` on ${new Date(entry.at).toLocaleString()}`}
                                            {entry.changes.length > 0 && (
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    ({entry.changes.join(', ')})
                                                </span>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

GeographyShow.layout = {
    breadcrumbs: [{ title: 'Geography', href: index('county') }],
};
