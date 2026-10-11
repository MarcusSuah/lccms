import { Form, Head, Link } from '@inertiajs/react';
import { Calendar, MapPin, Megaphone, Search } from 'lucide-react';
import { StatTile } from '@/components/admin/stat-tile';
import { humanize } from '@/components/admin/status-badge';
import { RichText } from '@/components/public/rich-text';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { contact } from '@/routes';
import { create as createComplaint } from '@/routes/complaints';
import {
    index as communitiesIndex,
    show as communityShow,
} from '@/routes/communities';
import { index as eventsIndex, show as eventShow } from '@/routes/events';
import { index as newsIndex, show as newsShow } from '@/routes/news';
import type { Option } from '@/types';

export type PublicPopulation = {
    approved_occupants: number;
    approved_households: number;
    approved_houses: number;
    average_household_size: number | null;
    sex: Record<string, number>;
    age_groups: Record<string, number>;
    is_enumerated: boolean;
    campaign: { name: string; reference_code: string; reference_date: string };
} | null;

type Props = {
    intro: { title: string; body_html: string } | null;
    population: PublicPopulation;
    registry: { communities: number; blocks: number };
    facilities: Record<string, Record<string, number>>;
    facilityCategories: Option[];
    infrastructure: {
        category: string;
        total: number;
        needs_attention: number;
    }[];
    featured: {
        name: string;
        slug: string;
        location: string;
        photo: string | null;
    }[];
    events: {
        title: string;
        slug: string;
        starts_at: string;
        venue: string | null;
    }[];
    posts: {
        title: string;
        slug: string;
        excerpt: string | null;
        published_at: string | null;
    }[];
    announcements: {
        id: number;
        title: string;
        body: string;
        is_urgent: boolean;
    }[];
    contact: string | null;
};

const sum = (values: Record<string, number> | undefined) =>
    Object.values(values ?? {}).reduce((total, value) => total + value, 0);

export default function Home({
    intro,
    population,
    registry,
    facilities,
    facilityCategories,
    infrastructure,
    featured,
    events,
    posts,
    announcements,
    contact: contactHtml,
}: Props) {
    return (
        <>
            <Head title="Home" />
            <section className="border-b bg-muted/30">
                <div className="mx-auto max-w-6xl px-4 py-12 md:py-16">
                    <h1 className="max-w-3xl text-3xl font-semibold tracking-tight md:text-4xl">
                        {intro?.title ??
                            'Community census, services and development'}
                    </h1>
                    <p className="mt-3 max-w-2xl text-muted-foreground">
                        Find your community, see approved population figures,
                        schools, health facilities, markets and public services,
                        and follow local news and events.
                    </p>
                    <Form
                        action={communitiesIndex.url()}
                        method="get"
                        className="mt-6 flex max-w-xl gap-2"
                    >
                        <div className="relative flex-1">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                name="search"
                                aria-label="Search communities"
                                placeholder="Search communities by name or code"
                                className="h-11 pl-9"
                            />
                        </div>
                        <Button type="submit" className="h-11">
                            Search
                        </Button>
                    </Form>
                </div>
            </section>

            {announcements.length > 0 && (
                <section
                    aria-label="Announcements"
                    className="mx-auto max-w-6xl px-4 pt-8"
                >
                    <ul className="space-y-2">
                        {announcements.map((announcement) => (
                            <li
                                key={announcement.id}
                                className="flex gap-3 rounded-lg border p-4"
                            >
                                <Megaphone className="mt-0.5 size-5 shrink-0 text-muted-foreground" />
                                <div>
                                    <div className="font-medium">
                                        {announcement.is_urgent && (
                                            <span className="mr-2 text-red-700 dark:text-red-400">
                                                Urgent:
                                            </span>
                                        )}
                                        {announcement.title}
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {announcement.body}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <section
                aria-labelledby="figures"
                className="mx-auto max-w-6xl space-y-4 px-4 py-10"
            >
                <div>
                    <h2 id="figures" className="text-xl font-semibold">
                        Approved figures
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {population
                            ? `From the ${population.campaign.name}, reference date ${population.campaign.reference_date}. Only approved census records are counted.`
                            : 'No census results have been finalised and published yet.'}
                    </p>
                </div>
                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <StatTile
                        label="Approved population"
                        value={population?.approved_occupants}
                        emptyLabel="Not yet published"
                    />
                    <StatTile
                        label="Households"
                        value={population?.approved_households}
                        emptyLabel="Not yet published"
                    />
                    <StatTile
                        label="Houses"
                        value={population?.approved_houses}
                        emptyLabel="Not yet published"
                    />
                    <StatTile
                        label="Participating communities"
                        value={registry.communities}
                    />
                </div>
            </section>

            <section
                aria-labelledby="services"
                className="mx-auto grid max-w-6xl gap-6 px-4 pb-10 lg:grid-cols-2"
            >
                <Card>
                    <CardHeader>
                        <CardTitle id="services">
                            Verified public and private facilities
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-xs text-muted-foreground">
                                    <th className="py-1 font-medium">Type</th>
                                    <th className="py-1 text-right font-medium">
                                        Public
                                    </th>
                                    <th className="py-1 text-right font-medium">
                                        Private and other
                                    </th>
                                    <th className="py-1 text-right font-medium">
                                        Total
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {facilityCategories.map((category) => {
                                    const counts =
                                        facilities[category.value] ?? {};
                                    const total = sum(counts);

                                    return (
                                        <tr
                                            key={category.value}
                                            className="border-t"
                                        >
                                            <td className="py-1.5">
                                                {category.label}
                                            </td>
                                            <td className="py-1.5 text-right tabular-nums">
                                                {(
                                                    counts.public ?? 0
                                                ).toLocaleString()}
                                            </td>
                                            <td className="py-1.5 text-right tabular-nums">
                                                {(
                                                    total - (counts.public ?? 0)
                                                ).toLocaleString()}
                                            </td>
                                            <td className="py-1.5 text-right font-medium tabular-nums">
                                                {total.toLocaleString()}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                        <p className="mt-2 text-xs text-muted-foreground">
                            Counts include only facilities verified by
                            inspectors. A missing facility here may simply not
                            be registered yet.
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Infrastructure</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {infrastructure.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No verified infrastructure records are published
                                yet.
                            </p>
                        ) : (
                            <ul className="space-y-2 text-sm">
                                {infrastructure.map((row) => (
                                    <li
                                        key={row.category}
                                        className="flex items-center justify-between border-b pb-2 last:border-0"
                                    >
                                        <span>{humanize(row.category)}</span>
                                        <span className="tabular-nums">
                                            {row.total} recorded
                                            {row.needs_attention > 0 && (
                                                <span className="ml-2 text-red-700 dark:text-red-400">
                                                    {row.needs_attention} need
                                                    repair
                                                </span>
                                            )}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </section>

            {featured.length > 0 && (
                <section
                    aria-labelledby="featured"
                    className="mx-auto max-w-6xl px-4 pb-10"
                >
                    <div className="mb-4 flex items-end justify-between">
                        <h2 id="featured" className="text-xl font-semibold">
                            Featured communities
                        </h2>
                        <Link
                            href={communitiesIndex()}
                            className="text-sm underline underline-offset-4"
                        >
                            All communities
                        </Link>
                    </div>
                    <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {featured.map((community) => (
                            <li key={community.slug}>
                                <Link
                                    href={communityShow(community.slug)}
                                    className="block overflow-hidden rounded-lg border hover:shadow-sm"
                                >
                                    {community.photo && (
                                        <img
                                            src={community.photo}
                                            alt=""
                                            className="aspect-video w-full object-cover"
                                            loading="lazy"
                                        />
                                    )}
                                    <div className="p-3">
                                        <div className="font-medium">
                                            {community.name}
                                        </div>
                                        <div className="flex items-center gap-1 text-xs text-muted-foreground">
                                            <MapPin className="size-3" />{' '}
                                            {community.location}
                                        </div>
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <section className="mx-auto grid max-w-6xl gap-6 px-4 pb-12 lg:grid-cols-2">
                <Card>
                    <CardHeader className="flex-row items-center justify-between">
                        <CardTitle>Latest news</CardTitle>
                        <Link
                            href={newsIndex()}
                            className="text-sm underline underline-offset-4"
                        >
                            All news
                        </Link>
                    </CardHeader>
                    <CardContent>
                        {posts.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No news yet.
                            </p>
                        ) : (
                            <ul className="space-y-4">
                                {posts.map((post) => (
                                    <li key={post.slug}>
                                        <Link
                                            href={newsShow(post.slug)}
                                            className="font-medium hover:underline"
                                        >
                                            {post.title}
                                        </Link>
                                        {post.published_at && (
                                            <div className="text-xs text-muted-foreground">
                                                {new Date(
                                                    post.published_at,
                                                ).toLocaleDateString()}
                                            </div>
                                        )}
                                        {post.excerpt && (
                                            <p className="text-sm text-muted-foreground">
                                                {post.excerpt}
                                            </p>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex-row items-center justify-between">
                        <CardTitle>Upcoming events</CardTitle>
                        <Link
                            href={eventsIndex()}
                            className="text-sm underline underline-offset-4"
                        >
                            All events
                        </Link>
                    </CardHeader>
                    <CardContent>
                        {events.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No upcoming events.
                            </p>
                        ) : (
                            <ul className="space-y-3">
                                {events.map((event) => (
                                    <li key={event.slug} className="flex gap-3">
                                        <Calendar className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                        <div>
                                            <Link
                                                href={eventShow(event.slug)}
                                                className="font-medium hover:underline"
                                            >
                                                {event.title}
                                            </Link>
                                            <div className="text-xs text-muted-foreground">
                                                {new Date(
                                                    event.starts_at,
                                                ).toLocaleString()}
                                                {event.venue &&
                                                    ` · ${event.venue}`}
                                            </div>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </section>

            <section className="border-t bg-muted/30">
                <div className="mx-auto grid max-w-6xl gap-6 px-4 py-10 md:grid-cols-2">
                    <div>
                        <h2 className="text-xl font-semibold">Contact</h2>
                        {contactHtml ? (
                            <RichText
                                html={contactHtml}
                                className="mt-2 text-sm"
                            />
                        ) : (
                            <p className="mt-2 text-sm text-muted-foreground">
                                Use the contact form to reach the project team.
                            </p>
                        )}
                        <Button asChild variant="outline" className="mt-4">
                            <Link href={contact()}>Send a message</Link>
                        </Button>
                    </div>
                    <div id="complaints">
                        <h2 className="text-xl font-semibold">
                            Report a problem
                        </h2>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Tell us about a problem with a public service or
                            your community. You can stay anonymous and attach up
                            to five photos. You will receive a private tracking
                            link.
                        </p>
                        <ComplaintLink />
                    </div>
                </div>
            </section>
        </>
    );
}

function ComplaintLink() {
    return (
        <Button asChild className="mt-4">
            <Link href={createComplaint()}>Submit a complaint</Link>
        </Button>
    );
}
