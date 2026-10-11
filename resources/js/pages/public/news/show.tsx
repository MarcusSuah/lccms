import { Head, Link } from '@inertiajs/react';
import { RichText } from '@/components/public/rich-text';
import { index } from '@/routes/news';

type Post = {
    title: string;
    excerpt: string | null;
    body_html: string;
    published_at: string | null;
    author: string | null;
    category: { name: string; slug: string } | null;
    tags: { name: string; slug: string }[];
    image: { url: string; alt: string } | null;
    seo_title: string;
    seo_description: string | null;
};

export default function NewsPost({ post }: { post: Post }) {
    return (
        <>
            <Head title={post.seo_title}>
                {post.seo_description && (
                    <meta name="description" content={post.seo_description} />
                )}
                <meta property="og:title" content={post.seo_title} />
            </Head>
            <article className="mx-auto max-w-3xl space-y-6 px-4 py-10">
                <Link
                    href={index()}
                    className="text-sm text-muted-foreground hover:underline"
                >
                    ← All news
                </Link>
                <header className="space-y-2">
                    <div className="text-sm text-muted-foreground">
                        {post.published_at &&
                            new Date(post.published_at).toLocaleDateString()}
                        {post.category && ` · ${post.category.name}`}
                        {post.author && ` · ${post.author}`}
                    </div>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        {post.title}
                    </h1>
                    {post.excerpt && (
                        <p className="text-lg text-muted-foreground">
                            {post.excerpt}
                        </p>
                    )}
                </header>
                {post.image && (
                    <img
                        src={post.image.url}
                        alt={post.image.alt}
                        className="w-full rounded-lg"
                    />
                )}
                <RichText html={post.body_html} />
                {post.tags.length > 0 && (
                    <ul className="flex flex-wrap gap-2 border-t pt-4">
                        {post.tags.map((tag) => (
                            <li key={tag.slug}>
                                <Link
                                    href={index({ query: { tag: tag.slug } })}
                                    className="rounded-full border px-3 py-1 text-xs hover:bg-accent"
                                >
                                    {tag.name}
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </article>
        </>
    );
}
