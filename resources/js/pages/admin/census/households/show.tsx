import { Head, router, useForm } from '@inertiajs/react';
import { EyeOff } from 'lucide-react';
import { useState } from 'react';
import { FormField } from '@/components/admin/form-field';
import { PageHeader } from '@/components/admin/page-header';
import { StatusBadge, humanize } from '@/components/admin/status-badge';
import InputError from '@/components/input-error';
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
import { Textarea } from '@/components/ui/textarea';
import {
    store as storeCorrection,
    update as updateCorrection,
} from '@/routes/admin/census/corrections';
import { update as updateDuplicate } from '@/routes/admin/census/duplicates';
import { index } from '@/routes/admin/census/households';
import { store as storeReview } from '@/routes/admin/census/households/reviews';
import type { Option } from '@/types';

type OccupantRow = {
    id: number;
    reference_code: string;
    full_name: string | null;
    date_of_birth: string | null;
    age: number | null;
    age_is_estimated: boolean;
    sex: string;
    sex_label: string;
    relationship_to_head: string;
    relationship_label: string;
    usual_resident: boolean;
    school_attendance: string | null;
    school_label: string | null;
    status: string;
};

type HouseholdDetail = {
    id: number;
    reference_code: string;
    status: string;
    version: number;
    residence_type: string;
    contact_name: string | null;
    contact_phone: string | null;
    review_note: string | null;
    sequence_in_house: number;
    enumerator: string | null;
    supervisor: string | null;
    location: string[];
    house: {
        reference_code: string;
        local_house_number: string | null;
        building_type: string | null;
        has_coordinates: boolean;
    };
    campaign: {
        id: number;
        name: string;
        reference_code: string;
        status: string;
        reference_date: string;
    };
};

type Review = {
    id: number;
    action: string;
    from: string | null;
    to: string;
    note: string | null;
    actor: string | null;
    at: string;
};

type Correction = {
    id: number;
    target: string;
    fields: string[];
    changes: Record<
        string,
        {
            from: string | number | boolean | null;
            to: string | number | boolean | null;
        }
    > | null;
    reason: string;
    status: string;
    requested_by: string | null;
    decided_by: string | null;
    decision_note: string | null;
};

type Duplicate = {
    id: number;
    occupant_id: number;
    matched_reference: string | null;
    score: number;
    reasons: string[];
    status: string;
};

function ReviewActions({
    household,
    can,
}: {
    household: HouseholdDetail;
    can: { verify: boolean; approve: boolean };
}) {
    const form = useForm({ action: '', note: '', version: household.version });

    const run = (action: string) => {
        form.transform((data) => ({ ...data, action }));
        form.submit(storeReview(household.id), { preserveScroll: true });
    };

    const status = household.status;
    const canVerify = can.verify && status === 'submitted';
    const canApprove = can.approve && status === 'verified';
    const canReturn =
        can.verify && (status === 'submitted' || status === 'verified');
    const canArchive =
        can.verify && status !== 'approved' && status !== 'archived';

    if (!canVerify && !canApprove && !canReturn && !canArchive) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Review</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
                <Textarea
                    aria-label="Review note"
                    placeholder="Note for the record (required to return or archive)"
                    value={form.data.note}
                    onChange={(event) =>
                        form.setData('note', event.target.value)
                    }
                />
                <InputError message={form.errors.note ?? form.errors.version} />
                <div className="flex flex-wrap gap-2">
                    {canVerify && (
                        <Button
                            disabled={form.processing}
                            onClick={() => run('verify')}
                        >
                            Verify
                        </Button>
                    )}
                    {canApprove && (
                        <Button
                            disabled={form.processing}
                            onClick={() => run('approve')}
                        >
                            Approve
                        </Button>
                    )}
                    {canReturn && (
                        <Button
                            variant="outline"
                            disabled={form.processing}
                            onClick={() => run('reject')}
                        >
                            Return to enumerator
                        </Button>
                    )}
                    {canArchive && (
                        <Button
                            variant="ghost"
                            disabled={form.processing}
                            onClick={() => {
                                if (
                                    window.confirm(
                                        'Archive this household? It will be excluded from every total.',
                                    )
                                ) {
                                    run('archive');
                                }
                            }}
                        >
                            Archive
                        </Button>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function CorrectionDialog({
    occupant,
    options,
}: {
    occupant: OccupantRow;
    options: { sex: Option[]; relationship: Option[]; school: Option[] };
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        type: 'occupant',
        id: occupant.id,
        reason: '',
        changes: {
            full_name: occupant.full_name ?? '',
            age_years: occupant.age?.toString() ?? '',
            sex: occupant.sex,
            relationship_to_head: occupant.relationship_to_head,
            school_attendance: occupant.school_attendance ?? '',
        },
    });
    const errors = form.errors as Record<string, string | undefined>;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm">
                    Request correction
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-xl">
                <form
                    className="space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((data) => {
                            const changes: Record<string, unknown> = {};
                            const original: Record<string, unknown> = {
                                full_name: occupant.full_name ?? '',
                                age_years: occupant.age?.toString() ?? '',
                                sex: occupant.sex,
                                relationship_to_head:
                                    occupant.relationship_to_head,
                                school_attendance:
                                    occupant.school_attendance ?? '',
                            };

                            for (const [key, value] of Object.entries(
                                data.changes,
                            )) {
                                if (value !== original[key]) {
                                    changes[key] = value === '' ? null : value;
                                }
                            }

                            return { ...data, changes };
                        });
                        form.submit(storeCorrection(), {
                            preserveScroll: true,
                            onSuccess: () => setOpen(false),
                        });
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>
                            Correct {occupant.reference_code}
                        </DialogTitle>
                    </DialogHeader>
                    <p className="text-sm text-muted-foreground">
                        Change only the fields that are wrong. A different
                        authorised person must approve the correction.
                    </p>
                    {occupant.full_name !== null && (
                        <FormField label="Full name" htmlFor="c-name">
                            <Input
                                id="c-name"
                                value={form.data.changes.full_name}
                                onChange={(event) =>
                                    form.setData('changes', {
                                        ...form.data.changes,
                                        full_name: event.target.value,
                                    })
                                }
                            />
                        </FormField>
                    )}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label="Age (years)"
                            htmlFor="c-age"
                            error={errors['age_years']}
                        >
                            <Input
                                id="c-age"
                                type="number"
                                min={0}
                                max={130}
                                value={form.data.changes.age_years}
                                onChange={(event) =>
                                    form.setData('changes', {
                                        ...form.data.changes,
                                        age_years: event.target.value,
                                    })
                                }
                            />
                        </FormField>
                        <FormField label="Sex" htmlFor="c-sex">
                            <NativeSelect
                                id="c-sex"
                                value={form.data.changes.sex}
                                onChange={(event) =>
                                    form.setData('changes', {
                                        ...form.data.changes,
                                        sex: event.target.value,
                                    })
                                }
                            >
                                {options.sex.map((option) => (
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
                            label="Relationship to head"
                            htmlFor="c-rel"
                            error={errors['relationship_to_head']}
                        >
                            <NativeSelect
                                id="c-rel"
                                value={form.data.changes.relationship_to_head}
                                onChange={(event) =>
                                    form.setData('changes', {
                                        ...form.data.changes,
                                        relationship_to_head:
                                            event.target.value,
                                    })
                                }
                            >
                                {options.relationship.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                        <FormField label="School attendance" htmlFor="c-school">
                            <NativeSelect
                                id="c-school"
                                value={form.data.changes.school_attendance}
                                onChange={(event) =>
                                    form.setData('changes', {
                                        ...form.data.changes,
                                        school_attendance: event.target.value,
                                    })
                                }
                            >
                                <option value="">Not recorded</option>
                                {options.school.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                    </div>
                    <FormField
                        label="Reason and evidence"
                        htmlFor="c-reason"
                        error={form.errors.reason}
                        required
                    >
                        <Textarea
                            id="c-reason"
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                    </FormField>
                    <InputError
                        message={
                            errors['changes'] ?? errors['action'] ?? undefined
                        }
                    />
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Submit correction
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function HouseholdShow({
    household,
    occupants,
    reviews,
    corrections,
    duplicates,
    options,
    can,
}: {
    household: HouseholdDetail;
    occupants: OccupantRow[];
    reviews: Review[];
    corrections: Correction[];
    duplicates: Duplicate[];
    options: { sex: Option[]; relationship: Option[]; school: Option[] };
    can: {
        viewPersonalData: boolean;
        verify: boolean;
        approve: boolean;
        requestCorrection: boolean;
        decideCorrection: boolean;
        reviewDuplicates: boolean;
    };
}) {
    const correctable =
        can.requestCorrection &&
        (household.status === 'verified' || household.status === 'approved');

    return (
        <>
            <Head title={`Household ${household.reference_code}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow={`${household.campaign.name} · household ${household.sequence_in_house} in house ${household.house.reference_code}`}
                    title={household.reference_code}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            <StatusBadge status={household.status} />
                            {household.location.join(' › ')}
                        </span>
                    }
                />

                {!can.viewPersonalData && (
                    <p className="flex items-center gap-2 rounded-md border bg-muted/50 p-3 text-sm text-muted-foreground">
                        <EyeOff className="size-4" /> Names, dates of birth and
                        contact details are hidden for your role.
                    </p>
                )}

                {household.review_note && (
                    <p className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm dark:border-amber-800 dark:bg-amber-950">
                        Latest review note: {household.review_note}
                    </p>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>
                                Occupants (
                                {
                                    occupants.filter(
                                        (o) => o.status !== 'archived',
                                    ).length
                                }
                                )
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Person</TableHead>
                                        <TableHead>Relationship</TableHead>
                                        <TableHead>Sex</TableHead>
                                        <TableHead className="text-right">
                                            Age
                                        </TableHead>
                                        <TableHead>School</TableHead>
                                        <TableHead className="sr-only">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {occupants.map((occupant) => (
                                        <TableRow key={occupant.id}>
                                            <TableCell>
                                                <div className="font-medium">
                                                    {occupant.full_name ?? (
                                                        <span className="text-muted-foreground">
                                                            Hidden
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="font-mono text-xs text-muted-foreground">
                                                    {occupant.reference_code}
                                                    {!occupant.usual_resident &&
                                                        ' · not usual resident'}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {occupant.relationship_label}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {occupant.sex_label}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {occupant.age ?? '—'}
                                                {occupant.age_is_estimated &&
                                                    ' (est.)'}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {occupant.school_label ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {correctable &&
                                                    occupant.status !==
                                                        'archived' && (
                                                        <CorrectionDialog
                                                            occupant={occupant}
                                                            options={options}
                                                        />
                                                    )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <div className="space-y-6">
                        <ReviewActions household={household} can={can} />
                        <Card>
                            <CardHeader>
                                <CardTitle>Household</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <dl className="grid gap-2 text-sm">
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Residence
                                        </dt>
                                        <dd>{household.residence_type}</dd>
                                    </div>
                                    {can.viewPersonalData && (
                                        <div>
                                            <dt className="text-xs text-muted-foreground">
                                                Contact
                                            </dt>
                                            <dd>
                                                {household.contact_name ?? '—'}
                                                {household.contact_phone &&
                                                    ` · ${household.contact_phone}`}
                                            </dd>
                                        </div>
                                    )}
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            House
                                        </dt>
                                        <dd>
                                            No.{' '}
                                            {household.house
                                                .local_house_number ??
                                                'unnumbered'}{' '}
                                            ·{' '}
                                            {household.house.building_type ??
                                                'type not recorded'}{' '}
                                            ·{' '}
                                            {household.house.has_coordinates
                                                ? 'GPS recorded'
                                                : 'no GPS'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Enumerator / supervisor
                                        </dt>
                                        <dd>
                                            {household.enumerator} /{' '}
                                            {household.supervisor ?? '—'}
                                        </dd>
                                    </div>
                                </dl>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Review history</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {reviews.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Not yet submitted.
                                </p>
                            ) : (
                                <ol className="space-y-2 text-sm">
                                    {reviews.map((review) => (
                                        <li key={review.id}>
                                            <span className="font-medium">
                                                {humanize(review.action)}
                                            </span>{' '}
                                            by {review.actor} ·{' '}
                                            {new Date(
                                                review.at,
                                            ).toLocaleString()}
                                            {review.note && (
                                                <div className="text-muted-foreground">
                                                    “{review.note}”
                                                </div>
                                            )}
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Corrections</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {corrections.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    None.
                                </p>
                            ) : (
                                <ul className="space-y-3 text-sm">
                                    {corrections.map((correction) => (
                                        <li key={correction.id}>
                                            <div className="flex items-center gap-2">
                                                <StatusBadge
                                                    status={correction.status}
                                                />
                                                {correction.target}:{' '}
                                                {correction.fields.join(', ')}
                                            </div>
                                            <p className="text-muted-foreground">
                                                {correction.reason} —{' '}
                                                {correction.requested_by}
                                            </p>
                                            {correction.changes && (
                                                <ul className="text-xs">
                                                    {Object.entries(
                                                        correction.changes,
                                                    ).map(([field, change]) => (
                                                        <li key={field}>
                                                            {humanize(field)}:{' '}
                                                            {String(
                                                                change.from ??
                                                                    '—',
                                                            )}{' '}
                                                            →{' '}
                                                            {String(
                                                                change.to ??
                                                                    '—',
                                                            )}
                                                        </li>
                                                    ))}
                                                </ul>
                                            )}
                                            {can.decideCorrection &&
                                                correction.status ===
                                                    'pending' && (
                                                    <div className="mt-1 flex gap-2">
                                                        <Button
                                                            size="sm"
                                                            onClick={() =>
                                                                router.patch(
                                                                    updateCorrection.url(
                                                                        correction.id,
                                                                    ),
                                                                    {
                                                                        decision:
                                                                            'approve',
                                                                    },
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            Approve
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => {
                                                                const note =
                                                                    window.prompt(
                                                                        'Reason for rejecting',
                                                                    );

                                                                if (note) {
                                                                    router.patch(
                                                                        updateCorrection.url(
                                                                            correction.id,
                                                                        ),
                                                                        {
                                                                            decision:
                                                                                'reject',
                                                                            note,
                                                                        },
                                                                        {
                                                                            preserveScroll: true,
                                                                        },
                                                                    );
                                                                }
                                                            }}
                                                        >
                                                            Reject
                                                        </Button>
                                                    </div>
                                                )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Possible duplicates</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {duplicates.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    None flagged.
                                </p>
                            ) : (
                                <ul className="space-y-3 text-sm">
                                    {duplicates.map((flag) => (
                                        <li key={flag.id}>
                                            <div className="flex items-center gap-2">
                                                <StatusBadge
                                                    status={flag.status}
                                                />
                                                Score {flag.score} · matches{' '}
                                                <span className="font-mono text-xs">
                                                    {flag.matched_reference}
                                                </span>
                                            </div>
                                            <p className="text-muted-foreground">
                                                {flag.reasons.join(', ')}
                                            </p>
                                            {can.reviewDuplicates &&
                                                flag.status === 'open' && (
                                                    <div className="mt-1 flex gap-2">
                                                        {(
                                                            [
                                                                [
                                                                    'confirmed_distinct',
                                                                    'Different people',
                                                                ],
                                                                [
                                                                    'confirmed_duplicate',
                                                                    'Same person',
                                                                ],
                                                            ] as const
                                                        ).map(
                                                            ([
                                                                status,
                                                                label,
                                                            ]) => (
                                                                <Button
                                                                    key={status}
                                                                    size="sm"
                                                                    variant="outline"
                                                                    onClick={() => {
                                                                        const note =
                                                                            window.prompt(
                                                                                'Note for the review record',
                                                                            );

                                                                        if (
                                                                            note
                                                                        ) {
                                                                            router.patch(
                                                                                updateDuplicate.url(
                                                                                    flag.id,
                                                                                ),
                                                                                {
                                                                                    status,
                                                                                    note,
                                                                                },
                                                                                {
                                                                                    preserveScroll: true,
                                                                                },
                                                                            );
                                                                        }
                                                                    }}
                                                                >
                                                                    {label}
                                                                </Button>
                                                            ),
                                                        )}
                                                    </div>
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

HouseholdShow.layout = {
    breadcrumbs: [{ title: 'Households', href: index() }],
};
