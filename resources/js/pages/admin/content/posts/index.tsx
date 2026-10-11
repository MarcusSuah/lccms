import { Head, Link } from '@inertiajs/react';
import { Newspaper, Plus } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { FilterBar } from '@/components/admin/filter-bar';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { StatusBadge } from '@/components/admin/status-badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { create, edit, index } from '@/routes/admin/content/posts';
import type { Option, SimplePaginated } from '@/types';

type Row = {
    id: number;
    title: string;
    status: string;
    published_at: string | null;
    author: string | null;
    category: string | null;
};

export default function PostsIndex({
    posts,
    filters,
    statusOptions,
}: {
    posts: SimplePaginated<Row>;
    filters: { status: string; search: string };
    statusOptions: Option[];
}) {
    return (
        <>
            <Head title="News posts" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Public website"
                    title="News and blog"
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> New post
                            </Link>
                        </Button>
                    }
                />
                <FilterBar
                    filters={filters}
                    searchPlaceholder="Title"
                    selects={[
                        {
                            name: 'status',
                            label: 'Status',
                            options: statusOptions,
                        },
                    ]}
                />
                {posts.data.length === 0 ? (
                    <EmptyState icon={Newspaper} title="No posts" />
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Title</TableHead>
                                    <TableHead>Author</TableHead>
                                    <TableHead>Publication</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {posts.data.map((post) => (
                                    <TableRow key={post.id}>
                                        <TableCell>
                                            <Link
                                                href={edit(post.id)}
                                                className="font-medium hover:underline"
                                            >
                                                {post.title}
                                            </Link>
                                            {post.category && (
                                                <div className="text-xs text-muted-foreground">
                                                    {post.category}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {post.author}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {post.published_at
                                                ? new Date(
                                                      post.published_at,
                                                  ).toLocaleString()
                                                : '—'}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge status={post.status} />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
                <Pagination
                    links={posts.links}
                    from={posts.from}
                    to={posts.to}
                    total={posts.total}
                />
            </div>
        </>
    );
}

PostsIndex.layout = {
    breadcrumbs: [{ title: 'News', href: index() }],
};
