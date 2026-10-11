import { Head, useForm } from '@inertiajs/react';
import { FormField } from '@/components/admin/form-field';
import { PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { index, update } from '@/routes/admin/content/pages';

type PageData = {
    slug: string;
    title: string;
    body_markdown: string;
    is_published: boolean;
    updated_at: string | null;
};

function PageEditor({
    page,
    canPublish,
}: {
    page: PageData;
    canPublish: boolean;
}) {
    const form = useForm({
        title: page.title,
        body_markdown: page.body_markdown,
        is_published: page.is_published,
    });

    return (
        <Card>
            <CardHeader>
                <CardTitle>{page.title}</CardTitle>
                <p className="text-xs text-muted-foreground">
                    {page.updated_at
                        ? `Last saved ${new Date(page.updated_at).toLocaleString()}`
                        : 'Not written yet'}
                </p>
            </CardHeader>
            <CardContent>
                <form
                    className="space-y-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.submit(update(page.slug), {
                            preserveScroll: true,
                        });
                    }}
                >
                    <FormField
                        label="Heading"
                        htmlFor={`title-${page.slug}`}
                        error={form.errors.title}
                    >
                        <Input
                            id={`title-${page.slug}`}
                            value={form.data.title}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                        />
                    </FormField>
                    <FormField
                        label="Text (Markdown)"
                        htmlFor={`body-${page.slug}`}
                        error={form.errors.body_markdown}
                    >
                        <Textarea
                            id={`body-${page.slug}`}
                            className="min-h-48 font-mono text-sm"
                            value={form.data.body_markdown}
                            onChange={(event) =>
                                form.setData(
                                    'body_markdown',
                                    event.target.value,
                                )
                            }
                        />
                    </FormField>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={form.data.is_published}
                            onCheckedChange={(checked) =>
                                form.setData('is_published', checked === true)
                            }
                        />
                        Published
                    </label>
                    <Button
                        type="submit"
                        size="sm"
                        disabled={!canPublish || form.processing}
                    >
                        {canPublish
                            ? 'Save page'
                            : 'Only publishers can save pages'}
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

export default function Pages({
    pages,
    can,
}: {
    pages: PageData[];
    can: { publish: boolean };
}) {
    return (
        <>
            <Head title="Website pages" />
            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Public website"
                    title="About, methodology and policies"
                    description="Describe the project objectives, census methodology, governance and responsible contacts, and data protection principles."
                />
                {pages.map((page) => (
                    <PageEditor
                        key={page.slug}
                        page={page}
                        canPublish={can.publish}
                    />
                ))}
            </div>
        </>
    );
}

Pages.layout = {
    breadcrumbs: [{ title: 'Website pages', href: index() }],
};
