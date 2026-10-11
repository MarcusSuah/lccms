import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FormField } from '@/components/admin/form-field';
import { PageHeader } from '@/components/admin/page-header';
import { GeoAreaPicker } from '@/components/geo/geo-area-picker';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { index, show, store, update } from '@/routes/admin/projects';
import type { GeoLevel, Option } from '@/types';

type Project = {
    id: number;
    title: string;
    description: string | null;
    category: string;
    status: string;
    level: GeoLevel;
    area_id: number;
    area: string;
    start_date: string | null;
    target_end_date: string | null;
    actual_end_date: string | null;
    sponsor: string | null;
    responsible_organization: string | null;
    budget_amount: string | null;
    budget_currency: string | null;
    budget_is_public: boolean;
    verification_status: string;
    is_public: boolean;
};

export default function ProjectForm({
    project,
    options,
}: {
    project: Project | null;
    options: {
        categories: Option[];
        statuses: Option[];
        verification: Option[];
    };
}) {
    const [changingArea, setChangingArea] = useState(project === null);
    const form = useForm({
        title: project?.title ?? '',
        description: project?.description ?? '',
        category: project?.category ?? 'water_sanitation',
        status: project?.status ?? 'planned',
        level: project?.level ?? ('' as GeoLevel | ''),
        area_id: project?.area_id ?? 0,
        start_date: project?.start_date ?? '',
        target_end_date: project?.target_end_date ?? '',
        actual_end_date: project?.actual_end_date ?? '',
        sponsor: project?.sponsor ?? '',
        responsible_organization: project?.responsible_organization ?? '',
        budget_amount: project?.budget_amount ?? '',
        budget_currency: project?.budget_currency ?? '',
        budget_is_public: project?.budget_is_public ?? false,
        verification_status: project?.verification_status ?? 'unverified',
        is_public: project?.is_public ?? false,
    });

    return (
        <>
            <Head title={project ? `Edit ${project.title}` : 'New project'} />
            <form
                className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.transform((data) => ({
                        ...data,
                        start_date: data.start_date || null,
                        target_end_date: data.target_end_date || null,
                        actual_end_date: data.actual_end_date || null,
                        budget_amount: data.budget_amount || null,
                        budget_currency: data.budget_currency || null,
                    }));
                    form.submit(project ? update(project.id) : store());
                }}
            >
                <PageHeader
                    eyebrow="Community development"
                    title={
                        project
                            ? `Edit ${project.title}`
                            : 'New development project'
                    }
                />
                <Card>
                    <CardContent className="grid gap-4 pt-6 sm:grid-cols-2">
                        <FormField
                            label="Title"
                            htmlFor="title"
                            error={form.errors.title}
                            required
                            className="sm:col-span-2"
                        >
                            <Input
                                id="title"
                                value={form.data.title}
                                onChange={(event) =>
                                    form.setData('title', event.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="Category"
                            htmlFor="category"
                            error={form.errors.category}
                        >
                            <NativeSelect
                                id="category"
                                value={form.data.category}
                                onChange={(event) =>
                                    form.setData('category', event.target.value)
                                }
                            >
                                {options.categories.map((option) => (
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
                                {options.statuses.map((option) => (
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
                            className="sm:col-span-2"
                        >
                            <Textarea
                                id="description"
                                className="min-h-28"
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Area</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {changingArea ? (
                            <GeoAreaPicker
                                maxLevel="block"
                                idPrefix="project-area"
                                onChange={(selection) =>
                                    form.setData((data) => ({
                                        ...data,
                                        level: selection?.level ?? '',
                                        area_id: selection?.id ?? 0,
                                    }))
                                }
                            />
                        ) : (
                            <div className="flex items-center justify-between rounded-md border p-3 text-sm">
                                {project?.area}
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() => setChangingArea(true)}
                                >
                                    Change area
                                </Button>
                            </div>
                        )}
                        {form.errors.area_id && (
                            <p className="text-sm text-destructive">
                                {form.errors.area_id}
                            </p>
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Timeline, sponsor and budget</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-3">
                        <FormField
                            label="Start"
                            htmlFor="start_date"
                            error={form.errors.start_date}
                        >
                            <Input
                                id="start_date"
                                type="date"
                                value={form.data.start_date}
                                onChange={(event) =>
                                    form.setData(
                                        'start_date',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label="Target completion"
                            htmlFor="target_end_date"
                            error={form.errors.target_end_date}
                        >
                            <Input
                                id="target_end_date"
                                type="date"
                                value={form.data.target_end_date}
                                onChange={(event) =>
                                    form.setData(
                                        'target_end_date',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label="Actual completion"
                            htmlFor="actual_end_date"
                            error={form.errors.actual_end_date}
                        >
                            <Input
                                id="actual_end_date"
                                type="date"
                                value={form.data.actual_end_date}
                                onChange={(event) =>
                                    form.setData(
                                        'actual_end_date',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField label="Sponsor" htmlFor="sponsor">
                            <Input
                                id="sponsor"
                                value={form.data.sponsor}
                                onChange={(event) =>
                                    form.setData('sponsor', event.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="Responsible organisation"
                            htmlFor="responsible_organization"
                            className="sm:col-span-2"
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
                            label="Budget"
                            htmlFor="budget_amount"
                            error={form.errors.budget_amount}
                        >
                            <Input
                                id="budget_amount"
                                inputMode="decimal"
                                value={form.data.budget_amount}
                                onChange={(event) =>
                                    form.setData(
                                        'budget_amount',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label="Currency"
                            htmlFor="budget_currency"
                            error={form.errors.budget_currency}
                        >
                            <NativeSelect
                                id="budget_currency"
                                value={form.data.budget_currency}
                                onChange={(event) =>
                                    form.setData(
                                        'budget_currency',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="">—</option>
                                <option value="LRD">LRD</option>
                                <option value="USD">USD</option>
                            </NativeSelect>
                        </FormField>
                        <label className="flex items-center gap-2 self-end pb-2 text-sm">
                            <Checkbox
                                checked={form.data.budget_is_public}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'budget_is_public',
                                        checked === true,
                                    )
                                }
                            />
                            Budget may be published
                        </label>
                        <FormField
                            label="Verification"
                            htmlFor="verification_status"
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
                        <div className="flex items-center gap-2 self-end pb-2 sm:col-span-2">
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
                        <Link href={project ? show(project.id) : index()}>
                            Cancel
                        </Link>
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        Save project
                    </Button>
                </div>
            </form>
        </>
    );
}

ProjectForm.layout = {
    breadcrumbs: [{ title: 'Projects', href: index() }],
};
