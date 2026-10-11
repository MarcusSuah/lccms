import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FormField } from '@/components/admin/form-field';
import { PageHeader } from '@/components/admin/page-header';
import { GeoAreaPicker } from '@/components/geo/geo-area-picker';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { index, store, update } from '@/routes/admin/content/events';
import type { GeoLevel, Option } from '@/types';

type EventData = {
    id: number;
    title: string;
    description_markdown: string;
    category: string;
    venue: string | null;
    organizer: string | null;
    starts_at: string;
    ends_at: string | null;
    level: GeoLevel | null;
    area_id: number | null;
    area: string;
};

export default function EventForm({
    event,
    categories,
}: {
    event: EventData | null;
    categories: Option[];
}) {
    const [changingArea, setChangingArea] = useState(!event);
    const form = useForm({
        title: event?.title ?? '',
        description_markdown: event?.description_markdown ?? '',
        category: event?.category ?? 'community_meeting',
        venue: event?.venue ?? '',
        organizer: event?.organizer ?? '',
        starts_at: event?.starts_at ?? '',
        ends_at: event?.ends_at ?? '',
        level: event?.level ?? ('' as GeoLevel | ''),
        area_id: event?.area_id ?? (null as number | null),
    });

    return (
        <>
            <Head title={event ? `Edit ${event.title}` : 'New event'} />
            <form
                className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6"
                onSubmit={(submitEvent) => {
                    submitEvent.preventDefault();
                    form.transform((data) => ({
                        ...data,
                        ends_at: data.ends_at || null,
                        level: data.level || null,
                    }));
                    form.submit(event ? update(event.id) : store());
                }}
            >
                <PageHeader
                    eyebrow="Public website"
                    title={event ? `Edit ${event.title}` : 'New event'}
                    description="Events appear publicly after a publisher approves them."
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
                                onChange={(changeEvent) =>
                                    form.setData(
                                        'title',
                                        changeEvent.target.value,
                                    )
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
                                onChange={(changeEvent) =>
                                    form.setData(
                                        'category',
                                        changeEvent.target.value,
                                    )
                                }
                            >
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
                        <FormField label="Organiser" htmlFor="organizer">
                            <Input
                                id="organizer"
                                value={form.data.organizer}
                                onChange={(changeEvent) =>
                                    form.setData(
                                        'organizer',
                                        changeEvent.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label="Starts"
                            htmlFor="starts_at"
                            error={form.errors.starts_at}
                            required
                        >
                            <Input
                                id="starts_at"
                                type="datetime-local"
                                value={form.data.starts_at}
                                onChange={(changeEvent) =>
                                    form.setData(
                                        'starts_at',
                                        changeEvent.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label="Ends"
                            htmlFor="ends_at"
                            error={form.errors.ends_at}
                        >
                            <Input
                                id="ends_at"
                                type="datetime-local"
                                value={form.data.ends_at}
                                onChange={(changeEvent) =>
                                    form.setData(
                                        'ends_at',
                                        changeEvent.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label="Venue"
                            htmlFor="venue"
                            className="sm:col-span-2"
                        >
                            <Input
                                id="venue"
                                value={form.data.venue}
                                onChange={(changeEvent) =>
                                    form.setData(
                                        'venue',
                                        changeEvent.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <div className="space-y-2 sm:col-span-2">
                            <div className="text-sm font-medium">
                                Area (leave empty for national events)
                            </div>
                            {changingArea ? (
                                <GeoAreaPicker
                                    maxLevel="community"
                                    scope="registry"
                                    idPrefix="event-area"
                                    onChange={(selection) =>
                                        form.setData((data) => ({
                                            ...data,
                                            level: selection?.level ?? '',
                                            area_id: selection?.id ?? null,
                                        }))
                                    }
                                />
                            ) : (
                                <div className="flex items-center justify-between rounded-md border p-3 text-sm">
                                    {event?.area}
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => setChangingArea(true)}
                                    >
                                        Change
                                    </Button>
                                </div>
                            )}
                        </div>
                        <FormField
                            label="Description (Markdown)"
                            htmlFor="description_markdown"
                            error={form.errors.description_markdown}
                            required
                            className="sm:col-span-2"
                        >
                            <Textarea
                                id="description_markdown"
                                className="min-h-48 font-mono text-sm"
                                value={form.data.description_markdown}
                                onChange={(changeEvent) =>
                                    form.setData(
                                        'description_markdown',
                                        changeEvent.target.value,
                                    )
                                }
                            />
                        </FormField>
                    </CardContent>
                </Card>
                <div className="flex justify-end gap-2">
                    <Button asChild variant="ghost">
                        <Link href={index()}>Cancel</Link>
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        Save event
                    </Button>
                </div>
            </form>
        </>
    );
}

EventForm.layout = {
    breadcrumbs: [{ title: 'Events', href: index() }],
};
