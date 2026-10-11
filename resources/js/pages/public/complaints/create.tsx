import { Head, useForm } from '@inertiajs/react';
import { ImagePlus, ShieldCheck, X } from 'lucide-react';
import { FormField } from '@/components/admin/form-field';
import { GeoAreaPicker } from '@/components/geo/geo-area-picker';
import type { HoneypotData } from '@/components/public/honeypot-fields';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { areaOptions } from '@/routes';
import { store } from '@/routes/complaints';
import type { GeoLevel, Option } from '@/types';

export default function CreateComplaint({
    categories,
    maxImages,
    maxImageKilobytes,
    honeypot,
}: {
    categories: Option[];
    maxImages: number;
    maxImageKilobytes: number;
    honeypot: HoneypotData;
}) {
    const form = useForm<{
        subject: string;
        category: string;
        description: string;
        level: GeoLevel | '';
        area_id: number;
        house_reference: string;
        is_anonymous: boolean;
        complainant_name: string;
        complainant_phone: string;
        complainant_email: string;
        images: File[];
    }>({
        subject: '',
        category: '',
        description: '',
        level: '',
        area_id: 0,
        house_reference: '',
        is_anonymous: false,
        complainant_name: '',
        complainant_phone: '',
        complainant_email: '',
        images: [],
    });
    const errors = form.errors as Record<string, string | undefined>;
    const imageError =
        errors.images ??
        Object.entries(errors).find(([key]) => key.startsWith('images.'))?.[1];

    const addImages = (files: FileList | null) => {
        if (!files) {
            return;
        }

        form.setData(
            'images',
            [...form.data.images, ...Array.from(files)].slice(0, maxImages),
        );
    };

    return (
        <>
            <Head title="Submit a complaint" />
            <form
                className="mx-auto max-w-3xl space-y-6 px-4 py-10"
                onSubmit={(event) => {
                    event.preventDefault();
                    // Honeypot fields travel with the request; people never see them.
                    form.transform((data) => ({
                        ...data,
                        ...(honeypot.enabled
                            ? {
                                  [honeypot.nameFieldName]: '',
                                  [honeypot.validFromFieldName]:
                                      honeypot.encryptedValidFrom,
                              }
                            : {}),
                    }));
                    form.submit(store(), { forceFormData: true });
                }}
            >
                <div className="space-y-2">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Submit a complaint
                    </h1>
                    <p className="text-muted-foreground">
                        Tell us about a problem with a public service or in your
                        community. You do not need an account.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>What happened?</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label="Subject"
                            htmlFor="subject"
                            error={form.errors.subject}
                            required
                            className="sm:col-span-2"
                        >
                            <Input
                                id="subject"
                                value={form.data.subject}
                                onChange={(event) =>
                                    form.setData('subject', event.target.value)
                                }
                                maxLength={160}
                            />
                        </FormField>
                        <FormField
                            label="Category"
                            htmlFor="category"
                            error={form.errors.category}
                            required
                        >
                            <NativeSelect
                                id="category"
                                value={form.data.category}
                                onChange={(event) =>
                                    form.setData('category', event.target.value)
                                }
                            >
                                <option value="">Choose…</option>
                                {categories.map((option) => (
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
                            label="House reference (optional)"
                            htmlFor="house_reference"
                            error={form.errors.house_reference}
                        >
                            <Input
                                id="house_reference"
                                value={form.data.house_reference}
                                onChange={(event) =>
                                    form.setData(
                                        'house_reference',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label="Description"
                            htmlFor="description"
                            error={form.errors.description}
                            required
                            className="sm:col-span-2"
                            hint="At least 20 characters. Include where and when, but avoid other people's personal details."
                        >
                            <Textarea
                                id="description"
                                className="min-h-36"
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
                        <CardTitle>Where?</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        <p className="text-sm text-muted-foreground">
                            Choose at least the county, and the community or
                            block if you know it.
                        </p>
                        <GeoAreaPicker
                            maxLevel="block"
                            optionsUrl={areaOptions.url()}
                            idPrefix="complaint-area"
                            onChange={(selection) =>
                                form.setData((data) => ({
                                    ...data,
                                    level: selection?.level ?? '',
                                    area_id: selection?.id ?? 0,
                                }))
                            }
                        />
                        <InputError
                            message={form.errors.level ?? form.errors.area_id}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            Photos (optional, up to {maxImages})
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <p className="text-sm text-muted-foreground">
                            JPEG, PNG or WebP, up to{' '}
                            {Math.round(maxImageKilobytes / 1024)} MB each.
                            Location and camera details are removed from photos.
                        </p>
                        {form.data.images.length > 0 && (
                            <ul className="space-y-1 text-sm">
                                {form.data.images.map((file, index) => (
                                    <li
                                        key={`${file.name}-${index}`}
                                        className="flex items-center justify-between rounded-md border px-3 py-1.5"
                                    >
                                        <span className="truncate">
                                            {file.name}
                                        </span>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Remove ${file.name}`}
                                            onClick={() =>
                                                form.setData(
                                                    'images',
                                                    form.data.images.filter(
                                                        (_, i) => i !== index,
                                                    ),
                                                )
                                            }
                                        >
                                            <X />
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                        {form.data.images.length < maxImages && (
                            <label className="inline-flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm hover:bg-accent">
                                <ImagePlus className="size-4" /> Add photos
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    multiple
                                    className="sr-only"
                                    onChange={(event) =>
                                        addImages(event.target.files)
                                    }
                                />
                            </label>
                        )}
                        <InputError message={imageError} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>How can we reach you?</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <label className="flex items-start gap-2 text-sm">
                            <Checkbox
                                checked={form.data.is_anonymous}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'is_anonymous',
                                        checked === true,
                                    )
                                }
                                className="mt-0.5"
                            />
                            <span>
                                Submit anonymously.{' '}
                                <span className="text-muted-foreground">
                                    We will not be able to contact you or ask
                                    for more information; you can still follow
                                    progress with your private tracking link.
                                </span>
                            </span>
                        </label>
                        {!form.data.is_anonymous && (
                            <div className="grid gap-4 sm:grid-cols-3">
                                <FormField
                                    label="Name"
                                    htmlFor="complainant_name"
                                    error={form.errors.complainant_name}
                                >
                                    <Input
                                        id="complainant_name"
                                        autoComplete="name"
                                        value={form.data.complainant_name}
                                        onChange={(event) =>
                                            form.setData(
                                                'complainant_name',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    label="Phone"
                                    htmlFor="complainant_phone"
                                    error={form.errors.complainant_phone}
                                >
                                    <Input
                                        id="complainant_phone"
                                        type="tel"
                                        autoComplete="tel"
                                        value={form.data.complainant_phone}
                                        onChange={(event) =>
                                            form.setData(
                                                'complainant_phone',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    label="Email"
                                    htmlFor="complainant_email"
                                    error={form.errors.complainant_email}
                                >
                                    <Input
                                        id="complainant_email"
                                        type="email"
                                        autoComplete="email"
                                        value={form.data.complainant_email}
                                        onChange={(event) =>
                                            form.setData(
                                                'complainant_email',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                            </div>
                        )}
                        <p className="flex items-start gap-2 text-xs text-muted-foreground">
                            <ShieldCheck className="mt-0.5 size-4 shrink-0" />{' '}
                            Your contact details are stored encrypted and are
                            only seen by complaint officers.
                        </p>
                    </CardContent>
                </Card>

                {form.progress && (
                    <progress
                        value={form.progress.percentage}
                        max={100}
                        className="w-full"
                    />
                )}
                <Button type="submit" size="lg" disabled={form.processing}>
                    {form.processing ? 'Sending…' : 'Submit complaint'}
                </Button>
            </form>
        </>
    );
}
