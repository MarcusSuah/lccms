import { Head, Link, useForm } from '@inertiajs/react';
import { FormField } from '@/components/admin/form-field';
import { PageHeader } from '@/components/admin/page-header';
import { humanize } from '@/components/admin/status-badge';
import { CommunityBlockField } from '@/components/geo/community-block-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { index, show, store, update } from '@/routes/admin/facilities';
import type { Option } from '@/types';

type Details = Record<
    string,
    string | number | boolean | string[] | null | undefined
>;

type FacilityData = {
    id: number;
    name: string;
    facility_type_id: number;
    category: string;
    ownership: string;
    operating_status: string;
    community_id: number;
    block_id: number | null;
    community: { id: number; name: string; reference_code: string } | null;
    block: { id: number; name: string } | null;
    address_description: string | null;
    latitude: string | null;
    longitude: string | null;
    contact_name: string | null;
    contact_phone: string | null;
    contact_email: string | null;
    established_year: number | null;
    details: Details;
    notes: string | null;
    verification_status: string;
    is_public: boolean;
    status: string;
};

type Options = {
    types: { value: number; label: string; category: string }[];
    ownership: Option[];
    operatingStatus: Option[];
    verification: Option[];
    recordStatus: Option[];
    educationLevels: string[];
    healthServices: string[];
    weekdays: string[];
};

function NumberField({
    label,
    field,
    details,
    setDetail,
    error,
}: {
    label: string;
    field: string;
    details: Details;
    setDetail: (key: string, value: Details[string]) => void;
    error?: string;
}) {
    return (
        <FormField label={label} htmlFor={`detail-${field}`} error={error}>
            <Input
                id={`detail-${field}`}
                type="number"
                min={0}
                value={(details[field] as number | null | undefined) ?? ''}
                onChange={(event) =>
                    setDetail(
                        field,
                        event.target.value === ''
                            ? null
                            : Number(event.target.value),
                    )
                }
            />
        </FormField>
    );
}

function MultiCheck({
    label,
    field,
    choices,
    details,
    setDetail,
}: {
    label: string;
    field: string;
    choices: string[];
    details: Details;
    setDetail: (key: string, value: Details[string]) => void;
}) {
    const selected = (details[field] as string[] | undefined) ?? [];

    return (
        <fieldset className="space-y-2 sm:col-span-2">
            <legend className="text-sm font-medium">{label}</legend>
            <div className="flex flex-wrap gap-3">
                {choices.map((choice) => (
                    <label
                        key={choice}
                        className="flex items-center gap-2 text-sm"
                    >
                        <Checkbox
                            checked={selected.includes(choice)}
                            onCheckedChange={(checked) =>
                                setDetail(
                                    field,
                                    checked === true
                                        ? [...selected, choice]
                                        : selected.filter(
                                              (value) => value !== choice,
                                          ),
                                )
                            }
                        />
                        {humanize(choice)}
                    </label>
                ))}
            </div>
        </fieldset>
    );
}

export default function FacilityForm({
    facility,
    options,
}: {
    facility: FacilityData | null;
    options: Options;
}) {
    const form = useForm({
        name: facility?.name ?? '',
        facility_type_id: facility?.facility_type_id ?? 0,
        ownership: facility?.ownership ?? 'public',
        operating_status: facility?.operating_status ?? 'operational',
        community_id: facility?.community_id ?? (null as number | null),
        block_id: facility?.block_id ?? (null as number | null),
        location_label: facility?.community
            ? `${facility.community.name}${facility.block ? ` › ${facility.block.name}` : ''}`
            : '',
        address_description: facility?.address_description ?? '',
        latitude: facility?.latitude ?? '',
        longitude: facility?.longitude ?? '',
        contact_name: facility?.contact_name ?? '',
        contact_phone: facility?.contact_phone ?? '',
        contact_email: facility?.contact_email ?? '',
        established_year: facility?.established_year?.toString() ?? '',
        details: (facility?.details ?? {}) as Details,
        notes: facility?.notes ?? '',
        verification_status: facility?.verification_status ?? 'unverified',
        is_public: facility?.is_public ?? false,
        status: facility?.status ?? 'active',
    });
    const errors = form.errors as Record<string, string | undefined>;
    const category = options.types.find(
        (type) => type.value === form.data.facility_type_id,
    )?.category;
    const setDetail = (key: string, value: Details[string]) =>
        form.setData('details', { ...form.data.details, [key]: value });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            latitude: data.latitude || null,
            longitude: data.longitude || null,
            established_year: data.established_year || null,
        }));

        if (facility) {
            form.submit(update(facility.id));
        } else {
            form.submit(store());
        }
    };

    return (
        <>
            <Head title={facility ? `Edit ${facility.name}` : 'New facility'} />
            <form
                onSubmit={submit}
                className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6"
            >
                <PageHeader
                    eyebrow="Community services"
                    title={facility ? `Edit ${facility.name}` : 'New facility'}
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Facility</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label="Name"
                            htmlFor="name"
                            error={form.errors.name}
                            required
                        >
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                required
                            />
                        </FormField>
                        <FormField
                            label="Type"
                            htmlFor="facility_type_id"
                            error={form.errors.facility_type_id}
                            required
                        >
                            <NativeSelect
                                id="facility_type_id"
                                value={form.data.facility_type_id || ''}
                                onChange={(event) =>
                                    form.setData(
                                        'facility_type_id',
                                        Number(event.target.value),
                                    )
                                }
                            >
                                <option value="">Select…</option>
                                {options.types.map((type) => (
                                    <option key={type.value} value={type.value}>
                                        {type.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                        <FormField
                            label="Ownership"
                            htmlFor="ownership"
                            error={form.errors.ownership}
                        >
                            <NativeSelect
                                id="ownership"
                                value={form.data.ownership}
                                onChange={(event) =>
                                    form.setData(
                                        'ownership',
                                        event.target.value,
                                    )
                                }
                            >
                                {options.ownership.map((option) => (
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
                            label="Operating status"
                            htmlFor="operating_status"
                            error={form.errors.operating_status}
                        >
                            <NativeSelect
                                id="operating_status"
                                value={form.data.operating_status}
                                onChange={(event) =>
                                    form.setData(
                                        'operating_status',
                                        event.target.value,
                                    )
                                }
                            >
                                {options.operatingStatus.map((option) => (
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
                            label="Year established"
                            htmlFor="established_year"
                            error={form.errors.established_year}
                        >
                            <Input
                                id="established_year"
                                type="number"
                                value={form.data.established_year}
                                onChange={(event) =>
                                    form.setData(
                                        'established_year',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label="Verification"
                            htmlFor="verification_status"
                            error={form.errors.verification_status}
                        >
                            <NativeSelect
                                id="verification_status"
                                value={form.data.verification_status}
                                onChange={(event) =>
                                    form.setData(
                                        'verification_status',
                                        event.target.value,
                                    )
                                }
                            >
                                {options.verification.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Location</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <div className="sm:col-span-2">
                            <CommunityBlockField
                                value={{
                                    community_id: form.data.community_id,
                                    block_id: form.data.block_id,
                                    label: form.data.location_label,
                                }}
                                onChange={(value) =>
                                    form.setData((data) => ({
                                        ...data,
                                        community_id: value.community_id,
                                        block_id: value.block_id,
                                        location_label: value.label,
                                    }))
                                }
                                error={
                                    form.errors.community_id ??
                                    form.errors.block_id
                                }
                            />
                        </div>
                        <FormField
                            label="Address / directions"
                            htmlFor="address_description"
                            className="sm:col-span-2"
                            error={form.errors.address_description}
                        >
                            <Input
                                id="address_description"
                                value={form.data.address_description}
                                onChange={(event) =>
                                    form.setData(
                                        'address_description',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label="Latitude"
                            htmlFor="latitude"
                            error={form.errors.latitude}
                            hint="Only from a verified GPS reading."
                        >
                            <Input
                                id="latitude"
                                inputMode="decimal"
                                value={form.data.latitude}
                                onChange={(event) =>
                                    form.setData('latitude', event.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="Longitude"
                            htmlFor="longitude"
                            error={form.errors.longitude}
                        >
                            <Input
                                id="longitude"
                                inputMode="decimal"
                                value={form.data.longitude}
                                onChange={(event) =>
                                    form.setData(
                                        'longitude',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                    </CardContent>
                </Card>

                {category && category !== 'other_service' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{humanize(category)} details</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            {category === 'school' && (
                                <>
                                    <MultiCheck
                                        label="Education levels"
                                        field="education_levels"
                                        choices={options.educationLevels}
                                        details={form.data.details}
                                        setDetail={setDetail}
                                    />
                                    <NumberField
                                        label="Classrooms"
                                        field="classrooms"
                                        details={form.data.details}
                                        setDetail={setDetail}
                                        error={errors['details.classrooms']}
                                    />
                                    <NumberField
                                        label="Teachers"
                                        field="teachers"
                                        details={form.data.details}
                                        setDetail={setDetail}
                                        error={errors['details.teachers']}
                                    />
                                    <NumberField
                                        label="Enrolment (total)"
                                        field="enrolment_total"
                                        details={form.data.details}
                                        setDetail={setDetail}
                                        error={
                                            errors['details.enrolment_total']
                                        }
                                    />
                                    <NumberField
                                        label="Enrolment (female)"
                                        field="enrolment_female"
                                        details={form.data.details}
                                        setDetail={setDetail}
                                        error={
                                            errors['details.enrolment_female']
                                        }
                                    />
                                </>
                            )}
                            {(category === 'hospital' ||
                                category === 'clinic' ||
                                category === 'other_health') && (
                                <>
                                    <MultiCheck
                                        label="Services offered"
                                        field="services"
                                        choices={options.healthServices}
                                        details={form.data.details}
                                        setDetail={setDetail}
                                    />
                                    <NumberField
                                        label="Beds"
                                        field="beds"
                                        details={form.data.details}
                                        setDetail={setDetail}
                                        error={errors['details.beds']}
                                    />
                                    <NumberField
                                        label="Staff"
                                        field="staff"
                                        details={form.data.details}
                                        setDetail={setDetail}
                                        error={errors['details.staff']}
                                    />
                                </>
                            )}
                            {category === 'market' && (
                                <>
                                    <MultiCheck
                                        label="Operating days"
                                        field="operating_days"
                                        choices={options.weekdays}
                                        details={form.data.details}
                                        setDetail={setDetail}
                                    />
                                    <NumberField
                                        label="Stalls"
                                        field="stalls"
                                        details={form.data.details}
                                        setDetail={setDetail}
                                        error={errors['details.stalls']}
                                    />
                                    <FormField
                                        label="Condition"
                                        htmlFor="detail-condition"
                                    >
                                        <NativeSelect
                                            id="detail-condition"
                                            value={
                                                (form.data.details
                                                    .condition as string) ?? ''
                                            }
                                            onChange={(event) =>
                                                setDetail(
                                                    'condition',
                                                    event.target.value || null,
                                                )
                                            }
                                        >
                                            <option value="">
                                                Not recorded
                                            </option>
                                            <option value="good">Good</option>
                                            <option value="fair">Fair</option>
                                            <option value="poor">Poor</option>
                                        </NativeSelect>
                                    </FormField>
                                </>
                            )}
                            {category === 'sanitation' && (
                                <>
                                    <NumberField
                                        label="Stalls"
                                        field="stalls"
                                        details={form.data.details}
                                        setDetail={setDetail}
                                        error={errors['details.stalls']}
                                    />
                                    <NumberField
                                        label="Functional stalls"
                                        field="functional_stalls"
                                        details={form.data.details}
                                        setDetail={setDetail}
                                        error={
                                            errors['details.functional_stalls']
                                        }
                                    />
                                    <FormField
                                        label="Maintenance"
                                        htmlFor="detail-maintenance"
                                    >
                                        <NativeSelect
                                            id="detail-maintenance"
                                            value={
                                                (form.data.details
                                                    .maintenance_status as string) ??
                                                ''
                                            }
                                            onChange={(event) =>
                                                setDetail(
                                                    'maintenance_status',
                                                    event.target.value || null,
                                                )
                                            }
                                        >
                                            <option value="">
                                                Not recorded
                                            </option>
                                            <option value="maintained">
                                                Maintained
                                            </option>
                                            <option value="irregular">
                                                Irregular
                                            </option>
                                            <option value="not_maintained">
                                                Not maintained
                                            </option>
                                        </NativeSelect>
                                    </FormField>
                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            checked={
                                                form.data.details.accessible ===
                                                true
                                            }
                                            onCheckedChange={(checked) =>
                                                setDetail(
                                                    'accessible',
                                                    checked === true,
                                                )
                                            }
                                        />
                                        Accessible to people with disabilities
                                    </label>
                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            checked={
                                                form.data.details
                                                    .fee_charged === true
                                            }
                                            onCheckedChange={(checked) =>
                                                setDetail(
                                                    'fee_charged',
                                                    checked === true,
                                                )
                                            }
                                        />
                                        A fee is charged
                                    </label>
                                </>
                            )}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Contacts and publication</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-3">
                        <FormField
                            label="Contact person"
                            htmlFor="contact_name"
                            error={form.errors.contact_name}
                        >
                            <Input
                                id="contact_name"
                                value={form.data.contact_name}
                                onChange={(event) =>
                                    form.setData(
                                        'contact_name',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label="Phone (staff only)"
                            htmlFor="contact_phone"
                            error={form.errors.contact_phone}
                        >
                            <Input
                                id="contact_phone"
                                value={form.data.contact_phone}
                                onChange={(event) =>
                                    form.setData(
                                        'contact_phone',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label="Email"
                            htmlFor="contact_email"
                            error={form.errors.contact_email}
                        >
                            <Input
                                id="contact_email"
                                type="email"
                                value={form.data.contact_email}
                                onChange={(event) =>
                                    form.setData(
                                        'contact_email',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label="Notes"
                            htmlFor="notes"
                            className="sm:col-span-3"
                            error={form.errors.notes}
                        >
                            <Textarea
                                id="notes"
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                            />
                        </FormField>
                        <div className="flex items-center gap-2 sm:col-span-3">
                            <Checkbox
                                id="is_public"
                                checked={form.data.is_public}
                                onCheckedChange={(checked) =>
                                    form.setData('is_public', checked === true)
                                }
                            />
                            <Label htmlFor="is_public">
                                Show on the public website (only verified
                                facilities are published; private phone numbers
                                never are)
                            </Label>
                        </div>
                    </CardContent>
                </Card>

                <div className="flex justify-end gap-2">
                    <Button asChild variant="ghost">
                        <Link href={facility ? show(facility.id) : index()}>
                            Cancel
                        </Link>
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {facility ? 'Save changes' : 'Register facility'}
                    </Button>
                </div>
            </form>
        </>
    );
}

FacilityForm.layout = {
    breadcrumbs: [{ title: 'Facilities', href: index() }],
};
