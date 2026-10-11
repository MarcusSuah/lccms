import { Head, Link } from '@inertiajs/react';
import { EmptyState } from '@/components/admin/empty-state';
import { FilterBar } from '@/components/admin/filter-bar';
import { Pagination } from '@/components/admin/pagination';
import { show } from '@/routes/news';
import type { SimplePaginated } from '@/types';

type PostCard = {
    title: string;
    slug: string;
    excerpt: string | null;
    published_at: string | null;
    category: { name: string; slug: string } | null;
    image: string | null;
};

export default function News({
    posts,
    categories,
    filters,
}: {
    posts: SimplePaginated<PostCard>;
    categories: { name: string; slug: string }[];
    filters: { search: string; category: string; tag: string };
}) {
    return (
        <>
            <Head title="News" />
            <div className="mx-auto max-w-4xl space-y-6 px-4 py-10">
                <h1 className="text-2xl font-semibold tracking-tight">
                    News and updates
                </h1>
                <FilterBar
                    filters={filters}
                    searchPlaceholder="Search news"
                    selects={[
                        {
                            name: 'category',
                            label: 'Category',
                            options: categories.map((category) => ({
                                value: category.slug,
                                label: category.name,
                            })),
                        },
                    ]}
                />
                {posts.data.length === 0 ? (
                    <EmptyState title="No news found" />
                ) : (
                    <ul className="divide-y">
                        {posts.data.map((post) => (
                            <li key={post.slug} className="flex gap-4 py-5">
                                {post.image && (
                                    <img
                                        src={post.image}
                                        alt=""
                                        className="hidden h-24 w-36 shrink-0 rounded-md object-cover sm:block"
                                        loading="lazy"
                                    />
                                )}
                                <div>
                                    <div className="text-xs text-muted-foreground">
                                        {post.published_at &&
                                            new Date(
                                                post.published_at,
                                            ).toLocaleDateString()}
                                        {post.category &&
                                            ` · ${post.category.name}`}
                                    </div>
                                    <Link
                                        href={show(post.slug)}
                                        className="text-lg font-medium hover:underline"
                                    >
                                        {post.title}
                                    </Link>
                                    {post.excerpt && (
                                        <p className="text-sm text-muted-foreground">
                                            {post.excerpt}
                                        </p>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
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
