import { Head, useForm } from '@inertiajs/react';
import { FormField } from '@/components/admin/form-field';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { StatusBadge } from '@/components/admin/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { index, store, update } from '@/routes/admin/content/announcements';
import type { SimplePaginated } from '@/types';

type Announcement = {
    id: number;
    title: string;
    body: string;
    community_id: number | null;
    community: string | null;
    starts_on: string;
    ends_on: string | null;
    is_published: boolean;
    is_urgent: boolean;
};

function AnnouncementForm({
    announcement,
    canPublish,
}: {
    announcement: Announcement | null;
    canPublish: boolean;
}) {
    const form = useForm({
        title: announcement?.title ?? '',
        body: announcement?.body ?? '',
        community_id: announcement?.community_id ?? null,
        starts_on:
            announcement?.starts_on ?? new Date().toISOString().slice(0, 10),
        ends_on: announcement?.ends_on ?? '',
        is_published: announcement?.is_published ?? false,
        is_urgent: announcement?.is_urgent ?? false,
    });

    return (
        <form
            className="grid gap-3 sm:grid-cols-2"
            onSubmit={(event) => {
                event.preventDefault();
                form.transform((data) => ({
                    ...data,
                    ends_on: data.ends_on || null,
                }));
                form.submit(announcement ? update(announcement.id) : store(), {
                    preserveScroll: true,
                    onSuccess: () => !announcement && form.reset(),
                });
            }}
        >
            <FormField
                label="Title"
                htmlFor={`a-title-${announcement?.id ?? 'new'}`}
                error={form.errors.title}
                className="sm:col-span-2"
            >
                <Input
                    id={`a-title-${announcement?.id ?? 'new'}`}
                    value={form.data.title}
                    onChange={(event) =>
                        form.setData('title', event.target.value)
                    }
                />
            </FormField>
            <FormField
                label="Message"
                htmlFor={`a-body-${announcement?.id ?? 'new'}`}
                error={form.errors.body}
                className="sm:col-span-2"
            >
                <Textarea
                    id={`a-body-${announcement?.id ?? 'new'}`}
                    value={form.data.body}
                    onChange={(event) =>
                        form.setData('body', event.target.value)
                    }
                />
            </FormField>
            <FormField
                label="From"
                htmlFor={`a-from-${announcement?.id ?? 'new'}`}
                error={form.errors.starts_on}
            >
                <Input
                    id={`a-from-${announcement?.id ?? 'new'}`}
                    type="date"
                    value={form.data.starts_on}
                    onChange={(event) =>
                        form.setData('starts_on', event.target.value)
                    }
                />
            </FormField>
            <FormField
                label="Until"
                htmlFor={`a-until-${announcement?.id ?? 'new'}`}
                error={form.errors.ends_on}
            >
                <Input
                    id={`a-until-${announcement?.id ?? 'new'}`}
                    type="date"
                    value={form.data.ends_on}
                    onChange={(event) =>
                        form.setData('ends_on', event.target.value)
                    }
                />
            </FormField>
            <label className="flex items-center gap-2 text-sm">
                <Checkbox
                    checked={form.data.is_urgent}
                    onCheckedChange={(checked) =>
                        form.setData('is_urgent', checked === true)
                    }
                />
                Urgent
            </label>
            <label className="flex items-center gap-2 text-sm">
                <Checkbox
                    disabled={!canPublish}
                    checked={form.data.is_published}
                    onCheckedChange={(checked) =>
                        form.setData('is_published', checked === true)
                    }
                />
                Published {!canPublish && '(publisher only)'}
            </label>
            <div className="sm:col-span-2">
                <Button type="submit" size="sm" disabled={form.processing}>
                    {announcement ? 'Save' : 'Add announcement'}
                </Button>
            </div>
        </form>
    );
}

export default function Announcements({
    announcements,
    can,
}: {
    announcements: SimplePaginated<Announcement>;
    can: { publish: boolean };
}) {
    return (
        <>
            <Head title="Announcements" />
            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Public website"
                    title="Announcements"
                    description="Short notices shown on the home page or a community page between the given dates."
                />
                <Card>
                    <CardHeader>
                        <CardTitle>New announcement</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <AnnouncementForm
                            announcement={null}
                            canPublish={can.publish}
                        />
                    </CardContent>
                </Card>
                {announcements.data.map((announcement) => (
                    <Card key={announcement.id}>
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle className="text-base">
                                {announcement.title}
                            </CardTitle>
                            <StatusBadge
                                status={
                                    announcement.is_published
                                        ? 'published'
                                        : 'draft'
                                }
                            />
                        </CardHeader>
                        <CardContent>
                            <AnnouncementForm
                                announcement={announcement}
                                canPublish={can.publish}
                            />
                        </CardContent>
                    </Card>
                ))}
                <Pagination
                    links={announcements.links}
                    from={announcements.from}
                    to={announcements.to}
                    total={announcements.total}
                />
            </div>
        </>
    );
}

Announcements.layout = {
    breadcrumbs: [{ title: 'Announcements', href: index() }],
};
