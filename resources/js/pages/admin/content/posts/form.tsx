import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FormField } from '@/components/admin/form-field';
import { PageHeader } from '@/components/admin/page-header';
import { StatusBadge } from '@/components/admin/status-badge';
import { RichText } from '@/components/public/rich-text';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import {
    index,
    status as statusRoute,
    store,
    update,
} from '@/routes/admin/content/posts';
import type { Option } from '@/types';

type Post = {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    body_markdown: string;
    body_html: string;
    category_id: number | null;
    featured_media_id: number | null;
    seo_title: string | null;
    seo_description: string | null;
    status: string;
    published_at: string | null;
    tags: string;
};

export default function PostForm({
    post,
    categories,
    media,
    can,
}: {
    post: Post | null;
    categories: Option<number>[];
    media: { value: number; label: string; thumbnail_url: string }[];
    can: { publish: boolean };
}) {
    const [scheduleAt, setScheduleAt] = useState('');
    const form = useForm({
        title: post?.title ?? '',
        slug: post?.slug ?? '',
        excerpt: post?.excerpt ?? '',
        body_markdown: post?.body_markdown ?? '',
        category_id: post?.category_id ?? ('' as number | ''),
        featured_media_id: post?.featured_media_id ?? ('' as number | ''),
        seo_title: post?.seo_title ?? '',
        seo_description: post?.seo_description ?? '',
        tags: post?.tags ?? '',
    });

    const transition = (action: string, extra: Record<string, string> = {}) => {
        if (post) {
            router.patch(
                statusRoute.url(post.id),
                { action, ...extra },
                { preserveScroll: true },
            );
        }
    };

    return (
        <>
            <Head title={post ? `Edit ${post.title}` : 'New post'} />
            <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Public website"
                    title={post ? post.title : 'New post'}
                    description={post && <StatusBadge status={post.status} />}
                    actions={
                        post && (
                            <>
                                {post.status === 'draft' && (
                                    <Button
                                        variant="outline"
                                        onClick={() => transition('submit')}
                                    >
                                        Submit for review
                                    </Button>
                                )}
                                {can.publish && post.status !== 'published' && (
                                    <Button
                                        onClick={() => transition('publish')}
                                    >
                                        Publish now
                                    </Button>
                                )}
                                {can.publish && post.status === 'published' && (
                                    <Button
                                        variant="outline"
                                        onClick={() => transition('unpublish')}
                                    >
                                        Unpublish
                                    </Button>
                                )}
                            </>
                        )
                    }
                />
                <form
                    className="grid gap-6 lg:grid-cols-[1fr_20rem]"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((data) => ({
                            ...data,
                            slug: data.slug || null,
                            category_id: data.category_id || null,
                            featured_media_id: data.featured_media_id || null,
                        }));
                        form.submit(post ? update(post.id) : store(), {
                            preserveScroll: true,
                        });
                    }}
                >
                    <div className="space-y-4">
                        <FormField
                            label="Title"
                            htmlFor="title"
                            error={form.errors.title}
                            required
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
                            label="Summary"
                            htmlFor="excerpt"
                            error={form.errors.excerpt}
                            hint="Shown in listings. Generated from the text if left empty."
                        >
                            <Textarea
                                id="excerpt"
                                value={form.data.excerpt}
                                onChange={(event) =>
                                    form.setData('excerpt', event.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="Text (Markdown)"
                            htmlFor="body_markdown"
                            error={form.errors.body_markdown}
                            hint="Use ## for headings, **bold**, *italic*, - for lists and [text](https://link). HTML is not allowed."
                            required
                        >
                            <Textarea
                                id="body_markdown"
                                className="min-h-80 font-mono text-sm"
                                value={form.data.body_markdown}
                                onChange={(event) =>
                                    form.setData(
                                        'body_markdown',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        {post?.body_html && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Saved preview</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <RichText html={post.body_html} />
                                </CardContent>
                            </Card>
                        )}
                    </div>
                    <div className="space-y-4">
                        <FormField label="Category" htmlFor="category_id">
                            <NativeSelect
                                id="category_id"
                                value={form.data.category_id}
                                onChange={(event) =>
                                    form.setData(
                                        'category_id',
                                        event.target.value
                                            ? Number(event.target.value)
                                            : '',
                                    )
                                }
                            >
                                <option value="">None</option>
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
                            label="Tags"
                            htmlFor="tags"
                            hint="Comma separated"
                        >
                            <Input
                                id="tags"
                                value={form.data.tags}
                                onChange={(event) =>
                                    form.setData('tags', event.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="Featured photo (approved photos only)"
                            htmlFor="featured_media_id"
                        >
                            <NativeSelect
                                id="featured_media_id"
                                value={form.data.featured_media_id}
                                onChange={(event) =>
                                    form.setData(
                                        'featured_media_id',
                                        event.target.value
                                            ? Number(event.target.value)
                                            : '',
                                    )
                                }
                            >
                                <option value="">None</option>
                                {media.map((option) => (
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
                            label="Address (slug)"
                            htmlFor="slug"
                            error={form.errors.slug}
                        >
                            <Input
                                id="slug"
                                value={form.data.slug}
                                onChange={(event) =>
                                    form.setData('slug', event.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="Search engine title"
                            htmlFor="seo_title"
                            error={form.errors.seo_title}
                        >
                            <Input
                                id="seo_title"
                                maxLength={70}
                                value={form.data.seo_title}
                                onChange={(event) =>
                                    form.setData(
                                        'seo_title',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label="Search engine description"
                            htmlFor="seo_description"
                            error={form.errors.seo_description}
                        >
                            <Textarea
                                id="seo_description"
                                maxLength={170}
                                value={form.data.seo_description}
                                onChange={(event) =>
                                    form.setData(
                                        'seo_description',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <Button
                            type="submit"
                            className="w-full"
                            disabled={form.processing}
                        >
                            Save
                        </Button>
                        {post && can.publish && (
                            <div className="space-y-2 rounded-md border p-3">
                                <FormField
                                    label="Schedule publication"
                                    htmlFor="schedule"
                                >
                                    <Input
                                        id="schedule"
                                        type="datetime-local"
                                        value={scheduleAt}
                                        onChange={(event) =>
                                            setScheduleAt(event.target.value)
                                        }
                                    />
                                </FormField>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={!scheduleAt}
                                    onClick={() =>
                                        transition('schedule', {
                                            published_at: scheduleAt,
                                        })
                                    }
                                >
                                    Schedule
                                </Button>
                            </div>
                        )}
                        <Button asChild variant="ghost" className="w-full">
                            <Link href={index()}>Back to posts</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

PostForm.layout = {
    breadcrumbs: [{ title: 'News', href: index() }],
};
