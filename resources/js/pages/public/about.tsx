import { Head } from '@inertiajs/react';
import { RichText } from '@/components/public/rich-text';

export default function About({
    pages,
    participatingCommunities,
}: {
    pages: { slug: string; title: string; body_html: string }[];
    participatingCommunities: number;
}) {
    return (
        <>
            <Head title="About" />
            <div className="mx-auto grid max-w-6xl gap-8 px-4 py-10 lg:grid-cols-[14rem_1fr]">
                <nav aria-label="On this page" className="hidden lg:block">
                    <ul className="sticky top-6 space-y-2 text-sm">
                        {pages.map((page) => (
                            <li key={page.slug}>
                                <a
                                    href={`#${page.slug}`}
                                    className="text-muted-foreground hover:text-foreground"
                                >
                                    {page.title}
                                </a>
                            </li>
                        ))}
                    </ul>
                </nav>
                <div className="space-y-12">
                    <p className="rounded-lg border bg-muted/30 p-4 text-sm">
                        {participatingCommunities.toLocaleString()} communities
                        currently have a published profile.
                    </p>
                    {pages.length === 0 && (
                        <p className="text-muted-foreground">
                            Information about the project will be published here
                            soon.
                        </p>
                    )}
                    {pages.map((page) => (
                        <section
                            key={page.slug}
                            id={page.slug}
                            className="scroll-mt-6 space-y-3"
                        >
                            <h2 className="text-2xl font-semibold tracking-tight">
                                {page.title}
                            </h2>
                            <RichText html={page.body_html} />
                        </section>
                    ))}
                </div>
            </div>
        </>
    );
}
