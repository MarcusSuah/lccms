import { Head, Link } from '@inertiajs/react';
import { RichText } from '@/components/public/rich-text';
import { show as communityShow } from '@/routes/communities';
import { index } from '@/routes/events';

type Event = {
    title: string;
    category: string;
    status: string;
    starts_at: string;
    ends_at: string | null;
    venue: string | null;
    organizer: string | null;
    community: { name: string; slug: string } | null;
    county: string | null;
    description_html: string;
    image: string | null;
};

export default function EventPage({ event }: { event: Event }) {
    return (
        <>
            <Head title={event.title} />
            <article className="mx-auto max-w-3xl space-y-6 px-4 py-10">
                <Link
                    href={index()}
                    className="text-sm text-muted-foreground hover:underline"
                >
                    ← All events
                </Link>
                {event.status === 'cancelled' && (
                    <p className="rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
                        This event has been cancelled.
                    </p>
                )}
                <header className="space-y-1">
                    <div className="text-sm text-muted-foreground">
                        {event.category}
                    </div>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        {event.title}
                    </h1>
                </header>
                <dl className="grid gap-2 rounded-lg border p-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt className="text-xs text-muted-foreground">When</dt>
                        <dd>
                            {new Date(event.starts_at).toLocaleString()}
                            {event.ends_at &&
                                ` – ${new Date(event.ends_at).toLocaleString()}`}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-xs text-muted-foreground">Where</dt>
                        <dd>
                            {event.venue ?? '—'}
                            {event.community && (
                                <>
                                    {' · '}
                                    <Link
                                        href={communityShow(
                                            event.community.slug,
                                        )}
                                        className="underline underline-offset-4"
                                    >
                                        {event.community.name}
                                    </Link>
                                </>
                            )}
                        </dd>
                    </div>
                    {event.organizer && (
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Organiser
                            </dt>
                            <dd>{event.organizer}</dd>
                        </div>
                    )}
                </dl>
                {event.image && (
                    <img
                        src={event.image}
                        alt=""
                        className="w-full rounded-lg"
                    />
                )}
                <RichText html={event.description_html} />
            </article>
        </>
    );
}
