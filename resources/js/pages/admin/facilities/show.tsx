import { Head, Link, useForm } from '@inertiajs/react';
import { ClipboardPlus, Pencil } from 'lucide-react';
import { useState } from 'react';
import { FormField } from '@/components/admin/form-field';
import { MediaManager } from '@/components/admin/media-manager';
import type { OwnerMedia } from '@/components/admin/media-manager';
import { PageHeader } from '@/components/admin/page-header';
import { StatusBadge, humanize } from '@/components/admin/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { edit, index } from '@/routes/admin/facilities';
import { store as storeInspection } from '@/routes/admin/facilities/inspections';
import type { Option } from '@/types';

type Inspection = {
    id: number;
    inspected_on: string;
    inspector: string | null;
    operating_status: string;
    condition: string;
    findings: string;
    recommendations: string | null;
    follow_up_due_on: string | null;
};

type Facility = {
    id: number;
    reference_code: string;
    name: string;
    type: string | null;
    category_label: string;
    ownership: string;
    operating_status: string;
    verification_status: string;
    is_public: boolean;
    location: string[];
    address_description: string | null;
    latitude: string | null;
    longitude: string | null;
    contact_name: string | null;
    contact_phone: string | null;
    contact_email: string | null;
    established_year: number | null;
    details: Record<string, unknown>;
    notes: string | null;
};

function InspectionForm({
    facilityId,
    options,
    onDone,
}: {
    facilityId: number;
    options: { operatingStatus: Option[]; conditions: Option[] };
    onDone: () => void;
}) {
    const form = useForm({
        inspected_on: new Date().toISOString().slice(0, 10),
        operating_status: 'operational',
        condition: 'good',
        findings: '',
        recommendations: '',
        follow_up_due_on: '',
        update_status: true,
    });

    return (
        <form
            className="grid gap-3 sm:grid-cols-3"
            onSubmit={(event) => {
                event.preventDefault();
                form.transform((data) => ({
                    ...data,
                    follow_up_due_on: data.follow_up_due_on || null,
                }));
                form.submit(storeInspection(facilityId), {
                    preserveScroll: true,
                    onSuccess: onDone,
                });
            }}
        >
            <FormField
                label="Date"
                htmlFor="inspected_on"
                error={form.errors.inspected_on}
            >
                <Input
                    id="inspected_on"
                    type="date"
                    value={form.data.inspected_on}
                    onChange={(event) =>
                        form.setData('inspected_on', event.target.value)
                    }
                />
            </FormField>
            <FormField
                label="Operating status observed"
                htmlFor="observed_status"
            >
                <NativeSelect
                    id="observed_status"
                    value={form.data.operating_status}
                    onChange={(event) =>
                        form.setData('operating_status', event.target.value)
                    }
                >
                    {options.operatingStatus.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </NativeSelect>
            </FormField>
            <FormField label="Condition" htmlFor="inspection_condition">
                <NativeSelect
                    id="inspection_condition"
                    value={form.data.condition}
                    onChange={(event) =>
                        form.setData('condition', event.target.value)
                    }
                >
                    {options.conditions.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </NativeSelect>
            </FormField>
            <FormField
                label="Findings"
                htmlFor="findings"
                className="sm:col-span-3"
                error={form.errors.findings}
                required
            >
                <Textarea
                    id="findings"
                    value={form.data.findings}
                    onChange={(event) =>
                        form.setData('findings', event.target.value)
                    }
                />
            </FormField>
            <FormField
                label="Recommendations"
                htmlFor="recommendations"
                className="sm:col-span-2"
            >
                <Textarea
                    id="recommendations"
                    value={form.data.recommendations}
                    onChange={(event) =>
                        form.setData('recommendations', event.target.value)
                    }
                />
            </FormField>
            <FormField
                label="Follow up by"
                htmlFor="follow_up_due_on"
                error={form.errors.follow_up_due_on}
            >
                <Input
                    id="follow_up_due_on"
                    type="date"
                    value={form.data.follow_up_due_on}
                    onChange={(event) =>
                        form.setData('follow_up_due_on', event.target.value)
                    }
                />
            </FormField>
            <label className="flex items-center gap-2 text-sm sm:col-span-3">
                <Checkbox
                    checked={form.data.update_status}
                    onCheckedChange={(checked) =>
                        form.setData('update_status', checked === true)
                    }
                />
                Update the facility’s operating status to what was observed
            </label>
            <div className="sm:col-span-3">
                <Button type="submit" disabled={form.processing}>
                    Save inspection
                </Button>
            </div>
        </form>
    );
}

export default function FacilityShow({
    facility,
    inspections,
    options,
    media,
    can,
}: {
    facility: Facility;
    inspections: Inspection[];
    media: OwnerMedia[];
    options: { operatingStatus: Option[]; conditions: Option[] };
    can: { update: boolean; inspect: boolean };
}) {
    const [inspecting, setInspecting] = useState(false);
    const details = Object.entries(facility.details ?? {}).filter(
        ([, value]) => value !== null && value !== '',
    );

    return (
        <>
            <Head title={facility.name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow={`${facility.category_label} · ${facility.reference_code}`}
                    title={facility.name}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            <StatusBadge status={facility.operating_status} />
                            <StatusBadge
                                status={facility.verification_status}
                            />
                            {facility.location.join(' › ')}
                        </span>
                    }
                    actions={
                        <>
                            {can.inspect && (
                                <Button
                                    variant="outline"
                                    onClick={() => setInspecting(true)}
                                >
                                    <ClipboardPlus /> Record inspection
                                </Button>
                            )}
                            {can.update && (
                                <Button asChild variant="outline">
                                    <Link href={edit(facility.id)}>
                                        <Pencil /> Edit
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-2 text-sm">
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Type / ownership
                                    </dt>
                                    <dd>
                                        {facility.type} ·{' '}
                                        {humanize(facility.ownership)}
                                    </dd>
                                </div>
                                {facility.established_year && (
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Established
                                        </dt>
                                        <dd>{facility.established_year}</dd>
                                    </div>
                                )}
                                {details.map(([key, value]) => (
                                    <div key={key}>
                                        <dt className="text-xs text-muted-foreground">
                                            {humanize(key)}
                                        </dt>
                                        <dd>
                                            {Array.isArray(value)
                                                ? value
                                                      .map((item) =>
                                                          humanize(
                                                              String(item),
                                                          ),
                                                      )
                                                      .join(', ')
                                                : typeof value === 'boolean'
                                                  ? value
                                                      ? 'Yes'
                                                      : 'No'
                                                  : String(value)}
                                        </dd>
                                    </div>
                                ))}
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Contact
                                    </dt>
                                    <dd>
                                        {[
                                            facility.contact_name,
                                            facility.contact_phone,
                                            facility.contact_email,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ') || '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Public website
                                    </dt>
                                    <dd>
                                        {facility.is_public
                                            ? 'Published when verified'
                                            : 'Not published'}
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Inspection history</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {inspecting && (
                                <InspectionForm
                                    facilityId={facility.id}
                                    options={options}
                                    onDone={() => setInspecting(false)}
                                />
                            )}
                            {inspections.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No inspections recorded.
                                </p>
                            ) : (
                                <ul className="space-y-3">
                                    {inspections.map((inspection) => (
                                        <li
                                            key={inspection.id}
                                            className="rounded-md border p-3 text-sm"
                                        >
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-medium">
                                                    {inspection.inspected_on}
                                                </span>
                                                <StatusBadge
                                                    status={
                                                        inspection.condition
                                                    }
                                                />
                                                <StatusBadge
                                                    status={
                                                        inspection.operating_status
                                                    }
                                                />
                                                <span className="text-muted-foreground">
                                                    by {inspection.inspector}
                                                </span>
                                            </div>
                                            <p className="mt-1">
                                                {inspection.findings}
                                            </p>
                                            {inspection.recommendations && (
                                                <p className="text-muted-foreground">
                                                    Recommended:{' '}
                                                    {inspection.recommendations}
                                                </p>
                                            )}
                                            {inspection.follow_up_due_on && (
                                                <p className="text-xs text-muted-foreground">
                                                    Follow up by{' '}
                                                    {
                                                        inspection.follow_up_due_on
                                                    }
                                                </p>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>
                <MediaManager
                    ownerType="facility"
                    ownerId={facility.id}
                    media={media}
                    canManage={can.update}
                />
            </div>
        </>
    );
}

FacilityShow.layout = {
    breadcrumbs: [{ title: 'Facilities', href: index() }],
};
