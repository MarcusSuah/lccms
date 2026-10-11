import { Head, Link, useForm } from '@inertiajs/react';
import { FormField } from '@/components/admin/form-field';
import { PageHeader } from '@/components/admin/page-header';
import { CommunityBlockField } from '@/components/geo/community-block-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { index, store, update } from '@/routes/admin/infrastructure';
import type { Option } from '@/types';

type Asset = {
    id: number;
    name: string;
    category: string;
    community_id: number;
    block_id: number | null;
    community: { name: string } | null;
    block: { name: string } | null;
    condition: string;
    availability: string;
    latitude: string | null;
    longitude: string | null;
    last_inspected_on: string | null;
    maintenance_needs: string | null;
    responsible_organization: string | null;
    notes: string | null;
    verification_status: string;
    is_public: boolean;
    status: string;
};

function Select({
    id,
    label,
    value,
    options,
    onChange,
    error,
}: {
    id: string;
    label: string;
    value: string;
    options: Option[];
    onChange: (value: string) => void;
    error?: string;
}) {
    return (
        <FormField label={label} htmlFor={id} error={error}>
            <NativeSelect
                id={id}
                value={value}
                onChange={(event) => onChange(event.target.value)}
            >
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </NativeSelect>
        </FormField>
    );
}

export default function InfrastructureForm({
    asset,
    options,
}: {
    asset: Asset | null;
    options: {
        categories: Option[];
        conditions: Option[];
        availability: Option[];
        verification: Option[];
        recordStatus: Option[];
    };
}) {
    const form = useForm({
        name: asset?.name ?? '',
        category: asset?.category ?? 'water',
        community_id: asset?.community_id ?? (null as number | null),
        block_id: asset?.block_id ?? (null as number | null),
        location_label: asset?.community
            ? `${asset.community.name}${asset.block ? ` › ${asset.block.name}` : ''}`
            : '',
        condition: asset?.condition ?? 'unknown',
        availability: asset?.availability ?? 'available',
        latitude: asset?.latitude ?? '',
        longitude: asset?.longitude ?? '',
        last_inspected_on: asset?.last_inspected_on ?? '',
        maintenance_needs: asset?.maintenance_needs ?? '',
        responsible_organization: asset?.responsible_organization ?? '',
        notes: asset?.notes ?? '',
        verification_status: asset?.verification_status ?? 'unverified',
        is_public: asset?.is_public ?? false,
        status: asset?.status ?? 'active',
    });

    return (
        <>
            <Head
                title={
                    asset ? `Edit ${asset.name}` : 'New infrastructure asset'
                }
            />
            <form
                className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.transform((data) => ({
                        ...data,
                        latitude: data.latitude || null,
                        longitude: data.longitude || null,
                        last_inspected_on: data.last_inspected_on || null,
                    }));
                    form.submit(asset ? update(asset.id) : store());
                }}
            >
                <PageHeader
                    eyebrow="Community development"
                    title={
                        asset
                            ? `Edit ${asset.name}`
                            : 'New infrastructure asset'
                    }
                />
                <Card>
                    <CardContent className="grid gap-4 pt-6 sm:grid-cols-2">
                        <FormField
                            label="Name"
                            htmlFor="name"
                            error={form.errors.name}
                            required
                            className="sm:col-span-2"
                        >
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />
                        </FormField>
                        <Select
                            id="category"
                            label="Category"
                            value={form.data.category}
                            options={options.categories}
                            onChange={(value) =>
                                form.setData('category', value)
                            }
                            error={form.errors.category}
                        />
                        <Select
                            id="status"
                            label="Record status"
                            value={form.data.status}
                            options={options.recordStatus}
                            onChange={(value) => form.setData('status', value)}
                        />
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
                                error={form.errors.community_id}
                            />
                        </div>
                        <Select
                            id="condition"
                            label="Condition"
                            value={form.data.condition}
                            options={options.conditions}
                            onChange={(value) =>
                                form.setData('condition', value)
                            }
                        />
                        <Select
                            id="availability"
                            label="Availability"
                            value={form.data.availability}
                            options={options.availability}
                            onChange={(value) =>
                                form.setData('availability', value)
                            }
                        />
                        <FormField
                            label="Last inspected"
                            htmlFor="last_inspected_on"
                            error={form.errors.last_inspected_on}
                        >
                            <Input
                                id="last_inspected_on"
                                type="date"
                                value={form.data.last_inspected_on}
                                onChange={(event) =>
                                    form.setData(
                                        'last_inspected_on',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label="Responsible organisation"
                            htmlFor="responsible_organization"
                        >
                            <Input
                                id="responsible_organization"
                                value={form.data.responsible_organization}
                                onChange={(event) =>
                                    form.setData(
                                        'responsible_organization',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label="Latitude"
                            htmlFor="latitude"
                            error={form.errors.latitude}
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
                        <FormField
                            label="Maintenance needs"
                            htmlFor="maintenance_needs"
                            className="sm:col-span-2"
                        >
                            <Textarea
                                id="maintenance_needs"
                                value={form.data.maintenance_needs}
                                onChange={(event) =>
                                    form.setData(
                                        'maintenance_needs',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label="Notes"
                            htmlFor="notes"
                            className="sm:col-span-2"
                        >
                            <Textarea
                                id="notes"
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                            />
                        </FormField>
                        <Select
                            id="verification_status"
                            label="Verification"
                            value={form.data.verification_status}
                            options={options.verification}
                            onChange={(value) =>
                                form.setData('verification_status', value)
                            }
                        />
                        <div className="flex items-center gap-2 self-end pb-2">
                            <Checkbox
                                id="is_public"
                                checked={form.data.is_public}
                                onCheckedChange={(checked) =>
                                    form.setData('is_public', checked === true)
                                }
                            />
                            <Label htmlFor="is_public">
                                Show on the public website
                            </Label>
                        </div>
                    </CardContent>
                </Card>
                <div className="flex justify-end gap-2">
                    <Button asChild variant="ghost">
                        <Link href={index()}>Cancel</Link>
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        Save asset
                    </Button>
                </div>
            </form>
        </>
    );
}

InfrastructureForm.layout = {
    breadcrumbs: [{ title: 'Infrastructure', href: index() }],
};
