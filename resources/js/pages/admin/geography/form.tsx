import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { FormField } from '@/components/admin/form-field';
import { PageHeader } from '@/components/admin/page-header';
import { GeoAreaPicker } from '@/components/geo/geo-area-picker';
import { GeoBreadcrumb } from '@/components/geo/geo-breadcrumb';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { index, show, store, update } from '@/routes/admin/geography';
import type { GeoArea, GeoLevel, Option } from '@/types';
import { GEO_LEVELS } from '@/types/models';

type FormData = {
    parent_id: number | null;
    name: string;
    reference_code: string;
    district_number: string;
    description: string;
    status: string;
    verification_status: string;
    latitude: string;
    longitude: string;
    boundary: string;
    history: string;
    public_email: string;
    public_phone: string;
    is_published: boolean;
    confirm_possible_duplicate: boolean;
};

export default function GeographyForm({
    level,
    area,
    parent,
    statusOptions,
    verificationOptions,
}: {
    level: GeoLevel;
    area: GeoArea | null;
    parent: GeoArea | null;
    statusOptions: Option[];
    verificationOptions: Option[];
}) {
    const parentLevel =
        level === 'county' ? null : GEO_LEVELS[GEO_LEVELS.indexOf(level) - 1];
    const form = useForm<FormData>({
        parent_id: parent?.id ?? null,
        name: area?.name ?? '',
        reference_code: area?.reference_code ?? '',
        district_number: area?.district_number?.toString() ?? '',
        description: area?.description ?? '',
        status: area?.status ?? 'active',
        verification_status: area?.verification_status ?? 'unverified',
        latitude: area?.latitude ?? '',
        longitude: area?.longitude ?? '',
        boundary: area?.boundary ?? '',
        history: area?.history ?? '',
        public_email: area?.public_email ?? '',
        public_phone: area?.public_phone ?? '',
        is_published: area?.is_published ?? false,
        confirm_possible_duplicate: false,
    });
    const possibleDuplicate =
        form.errors.name?.startsWith('Possible duplicate');

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => {
            const payload: Record<string, unknown> = {
                ...data,
                reference_code: data.reference_code || null,
                district_number: data.district_number || null,
                latitude: data.latitude || null,
                longitude: data.longitude || null,
                boundary: data.boundary || null,
            };

            if (level !== 'community') {
                delete payload.history;
                delete payload.public_email;
                delete payload.public_phone;
                delete payload.is_published;
            }

            if (level !== 'district') {
                delete payload.district_number;
            }

            if (area) {
                delete payload.parent_id;
            }

            return payload;
        });

        if (area) {
            form.submit(update({ level, geoArea: area.id }));
        } else {
            form.submit(store(level));
        }
    };

    const title = area ? `Edit ${area.name}` : `New ${level}`;

    return (
        <>
            <Head title={title} />
            <form
                onSubmit={submit}
                className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6"
            >
                {area?.breadcrumb && <GeoBreadcrumb trail={area.breadcrumb} />}
                <PageHeader
                    eyebrow="Geographic registry"
                    title={title}
                    description={
                        area
                            ? 'The parent cannot be changed here. Use “Request move” on the area page.'
                            : 'Never invent official records. Enter only verified names and codes, or mark the record as unverified.'
                    }
                />

                {!area && parentLevel && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="capitalize">
                                Parent {parentLevel}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {parent ? (
                                parent.breadcrumb && (
                                    <GeoBreadcrumb
                                        trail={parent.breadcrumb}
                                        linkLast
                                    />
                                )
                            ) : (
                                <GeoAreaPicker
                                    maxLevel={parentLevel}
                                    exactLevel
                                    idPrefix="parent"
                                    onChange={(selection) =>
                                        form.setData(
                                            'parent_id',
                                            selection?.id ?? null,
                                        )
                                    }
                                />
                            )}
                            {form.errors.parent_id && (
                                <p className="text-sm text-destructive">
                                    {form.errors.parent_id}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Identification</CardTitle>
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
                            label="Reference code"
                            htmlFor="reference_code"
                            error={form.errors.reference_code}
                            required={level === 'county' && !area}
                            hint={
                                level === 'county'
                                    ? 'Use the official county code.'
                                    : 'Leave blank to generate one from the parent code.'
                            }
                        >
                            <Input
                                id="reference_code"
                                value={form.data.reference_code}
                                onChange={(event) =>
                                    form.setData(
                                        'reference_code',
                                        event.target.value.toUpperCase(),
                                    )
                                }
                                className="font-mono"
                            />
                        </FormField>
                        {level === 'district' && (
                            <FormField
                                label="District number"
                                htmlFor="district_number"
                                error={form.errors.district_number}
                                hint="Unique within the county, e.g. 2 for “District #2”."
                            >
                                <Input
                                    id="district_number"
                                    type="number"
                                    min={1}
                                    max={999}
                                    value={form.data.district_number}
                                    onChange={(event) =>
                                        form.setData(
                                            'district_number',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                        )}
                        <FormField
                            label="Status"
                            htmlFor="status"
                            error={form.errors.status}
                        >
                            <NativeSelect
                                id="status"
                                value={form.data.status}
                                onChange={(event) =>
                                    form.setData('status', event.target.value)
                                }
                            >
                                {statusOptions.map((option) => (
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
                                {verificationOptions.map((option) => (
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
                            label="Description"
                            htmlFor="description"
                            error={form.errors.description}
                            className="sm:col-span-2"
                        >
                            <Textarea
                                id="description"
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        {possibleDuplicate && (
                            <div className="flex items-start gap-2 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm sm:col-span-2 dark:border-amber-800 dark:bg-amber-950">
                                <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-600" />
                                <label className="flex items-center gap-2">
                                    <Checkbox
                                        checked={
                                            form.data.confirm_possible_duplicate
                                        }
                                        onCheckedChange={(checked) =>
                                            form.setData(
                                                'confirm_possible_duplicate',
                                                checked === true,
                                            )
                                        }
                                    />
                                    I have checked: this is a different place
                                    and not a duplicate.
                                </label>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Location</CardTitle>
                        <p className="text-sm text-muted-foreground">
                            Record coordinates and boundaries only from verified
                            surveys. Never estimate them.
                        </p>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
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
                            label="Boundary (GeoJSON Polygon or MultiPolygon)"
                            htmlFor="boundary"
                            error={form.errors.boundary}
                            className="sm:col-span-2"
                        >
                            <Textarea
                                id="boundary"
                                value={form.data.boundary}
                                onChange={(event) =>
                                    form.setData('boundary', event.target.value)
                                }
                                className="max-h-48 font-mono text-xs"
                            />
                        </FormField>
                    </CardContent>
                </Card>

                {level === 'community' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Public profile</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="History"
                                htmlFor="history"
                                error={form.errors.history}
                                className="sm:col-span-2"
                            >
                                <Textarea
                                    id="history"
                                    value={form.data.history}
                                    onChange={(event) =>
                                        form.setData(
                                            'history',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                label="Public email"
                                htmlFor="public_email"
                                error={form.errors.public_email}
                            >
                                <Input
                                    id="public_email"
                                    type="email"
                                    value={form.data.public_email}
                                    onChange={(event) =>
                                        form.setData(
                                            'public_email',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                label="Public phone"
                                htmlFor="public_phone"
                                error={form.errors.public_phone}
                            >
                                <Input
                                    id="public_phone"
                                    value={form.data.public_phone}
                                    onChange={(event) =>
                                        form.setData(
                                            'public_phone',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <div className="flex items-center gap-2 sm:col-span-2">
                                <Checkbox
                                    id="is_published"
                                    checked={form.data.is_published}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'is_published',
                                            checked === true,
                                        )
                                    }
                                />
                                <Label htmlFor="is_published">
                                    Publish the community profile on the public
                                    website
                                </Label>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <div className="flex items-center justify-end gap-2">
                    <Button asChild variant="ghost">
                        <Link
                            href={
                                area
                                    ? show({ level, geoArea: area.id })
                                    : index(level)
                            }
                        >
                            Cancel
                        </Link>
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {area ? 'Save changes' : `Create ${level}`}
                    </Button>
                </div>
            </form>
        </>
    );
}

GeographyForm.layout = {
    breadcrumbs: [{ title: 'Geography', href: index('county') }],
};
