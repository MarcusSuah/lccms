import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { FormField } from '@/components/admin/form-field';
import { PageHeader } from '@/components/admin/page-header';
import { GeoAreaPicker } from '@/components/geo/geo-area-picker';
import type { GeoSelection } from '@/components/geo/geo-area-picker';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { index, show, store, update } from '@/routes/admin/census/campaigns';
import type { CampaignSummary, GeoLevel } from '@/types';

type ScopeRow = { level: GeoLevel; area_id: number; label: string };

type CampaignForm = CampaignSummary & {
    description: string | null;
    instructions: string | null;
    coverage_locked: boolean;
    scopes: ScopeRow[];
};

export default function CampaignFormPage({
    campaign,
}: {
    campaign: CampaignForm | null;
}) {
    const form = useForm({
        reference_code: campaign?.reference_code ?? '',
        name: campaign?.name ?? '',
        description: campaign?.description ?? '',
        instructions: campaign?.instructions ?? '',
        start_date: campaign?.start_date ?? '',
        end_date: campaign?.end_date ?? '',
        reference_date: campaign?.reference_date ?? '',
        scopes: campaign?.scopes ?? ([] as ScopeRow[]),
    });
    const [pending, setPending] = useState<GeoSelection | null>(null);
    const [pickerKey, setPickerKey] = useState(0);
    const locked = campaign?.coverage_locked ?? false;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            scopes: data.scopes.map(({ level, area_id }) => ({
                level,
                area_id,
            })),
        }));

        if (campaign) {
            form.submit(update(campaign.id));
        } else {
            form.submit(store());
        }
    };

    return (
        <>
            <Head title={campaign ? `Edit ${campaign.name}` : 'New campaign'} />
            <form
                onSubmit={submit}
                className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6"
            >
                <PageHeader
                    eyebrow="Census"
                    title={campaign ? `Edit ${campaign.name}` : 'New campaign'}
                />
                <Card>
                    <CardHeader>
                        <CardTitle>Campaign</CardTitle>
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
                            hint="Short, e.g. LCC2026. Used in household references."
                            required
                        >
                            <Input
                                id="reference_code"
                                className="font-mono"
                                value={form.data.reference_code}
                                onChange={(event) =>
                                    form.setData(
                                        'reference_code',
                                        event.target.value.toUpperCase(),
                                    )
                                }
                                required
                            />
                        </FormField>
                        <FormField
                            label="Field work starts"
                            htmlFor="start_date"
                            error={form.errors.start_date}
                            required
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
                            label="Field work ends"
                            htmlFor="end_date"
                            error={form.errors.end_date}
                            required
                        >
                            <Input
                                id="end_date"
                                type="date"
                                value={form.data.end_date}
                                onChange={(event) =>
                                    form.setData('end_date', event.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="Census reference date"
                            htmlFor="reference_date"
                            error={form.errors.reference_date}
                            hint="Ages and residence are recorded as of this date."
                            required
                        >
                            <Input
                                id="reference_date"
                                type="date"
                                value={form.data.reference_date}
                                onChange={(event) =>
                                    form.setData(
                                        'reference_date',
                                        event.target.value,
                                    )
                                }
                            />
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
                        <FormField
                            label="Enumeration instructions"
                            htmlFor="instructions"
                            error={form.errors.instructions}
                            hint="Shown to enumerators in the field application."
                            className="sm:col-span-2"
                        >
                            <Textarea
                                id="instructions"
                                value={form.data.instructions}
                                onChange={(event) =>
                                    form.setData(
                                        'instructions',
                                        event.target.value,
                                    )
                                }
                                className="min-h-28"
                            />
                        </FormField>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Geographic coverage</CardTitle>
                        <p className="text-sm text-muted-foreground">
                            {locked
                                ? 'Coverage is fixed once field work has started.'
                                : 'Add every area this campaign enumerates.'}
                        </p>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {form.data.scopes.length > 0 && (
                            <ul className="divide-y rounded-md border">
                                {form.data.scopes.map((scope, position) => (
                                    <li
                                        key={`${scope.level}-${scope.area_id}`}
                                        className="flex items-center justify-between px-3 py-2 text-sm"
                                    >
                                        {scope.label}
                                        {!locked && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Remove ${scope.label}`}
                                                onClick={() =>
                                                    form.setData(
                                                        'scopes',
                                                        form.data.scopes.filter(
                                                            (_, i) =>
                                                                i !== position,
                                                        ),
                                                    )
                                                }
                                            >
                                                <Trash2 />
                                            </Button>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                        {!locked && (
                            <div className="space-y-3 rounded-md border border-dashed p-3">
                                <GeoAreaPicker
                                    key={pickerKey}
                                    maxLevel="block"
                                    onChange={setPending}
                                    idPrefix="coverage"
                                />
                                <Button
                                    type="button"
                                    variant="secondary"
                                    disabled={!pending}
                                    onClick={() => {
                                        if (!pending) {
                                            return;
                                        }

                                        form.setData('scopes', [
                                            ...form.data.scopes,
                                            {
                                                level: pending.level,
                                                area_id: pending.id,
                                                label: `${pending.level}: ${pending.label}`,
                                            },
                                        ]);
                                        setPending(null);
                                        setPickerKey((key) => key + 1);
                                    }}
                                >
                                    <Plus /> Add area
                                </Button>
                            </div>
                        )}
                        <InputError message={form.errors.scopes} />
                    </CardContent>
                </Card>

                <div className="flex justify-end gap-2">
                    <Button asChild variant="ghost">
                        <Link href={campaign ? show(campaign.id) : index()}>
                            Cancel
                        </Link>
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {campaign ? 'Save changes' : 'Create campaign'}
                    </Button>
                </div>
            </form>
        </>
    );
}

CampaignFormPage.layout = {
    breadcrumbs: [{ title: 'Campaigns', href: index() }],
};
