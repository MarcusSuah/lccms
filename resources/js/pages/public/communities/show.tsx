import { Head, Link } from '@inertiajs/react';
import { Calendar, Mail, Phone } from 'lucide-react';
import { useState } from 'react';
import { StatTile } from '@/components/admin/stat-tile';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { show as eventShow } from '@/routes/events';
import type { PublicPopulation } from '../home';

type Leader = {
    name: string;
    position: string | null;
    block: string | null;
    starts_on: string;
    ends_on: string | null;
    selection_method: string;
};
type Photo = {
    uuid: string;
    caption: string | null;
    alt: string;
    thumbnail_url: string;
    url: string;
};

type Props = {
    community: {
        name: string;
        reference_code: string;
        description: string | null;
        history: string | null;
        public_email: string | null;
        public_phone: string | null;
        verification_status: string;
        county: string | null;
        district: string | null;
        clan: string | null;
        town: string | null;
        location: { latitude: number; longitude: number } | null;
        blocks: number;
    };
    population: PublicPopulation;
    facilities: {
        name: string;
        type: string | null;
        category: string;
        category_label: string;
        ownership: string;
        operating_status: string;
    }[];
    infrastructure: {
        name: string;
        category: string;
        condition: string;
        availability: string;
    }[];
    leaders: { current: Leader[]; former: Leader[] };
    gallery: Photo[];
    events: {
        title: string;
        slug: string;
        starts_at: string;
        venue: string | null;
    }[];
    announcements: {
        id: number;
        title: string;
        body: string;
        is_urgent: boolean;
        starts_on: string;
    }[];
};

const groups: { label: string; categories: string[] }[] = [
    { label: 'Schools', categories: ['school'] },
    {
        label: 'Health facilities',
        categories: ['hospital', 'clinic', 'other_health'],
    },
    { label: 'Markets', categories: ['market'] },
    { label: 'Public toilets', categories: ['sanitation'] },
    { label: 'Other services', categories: ['other_service'] },
];

export default function CommunityProfile({
    community,
    population,
    facilities,
    infrastructure,
    leaders,
    gallery,
    events,
    announcements,
}: Props) {
    const [photo, setPhoto] = useState<Photo | null>(null);

    return (
        <>
            <Head title={community.name} />
            <div className="mx-auto max-w-6xl space-y-8 px-4 py-10">
                <header className="space-y-2">
                    <p className="text-sm text-muted-foreground">
                        {[
                            community.town,
                            community.clan,
                            community.district,
                            community.county,
                        ]
                            .filter(Boolean)
                            .join(' › ')}
                    </p>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        {community.name}
                    </h1>
                    <p className="font-mono text-xs text-muted-foreground">
                        {community.reference_code} · registry record{' '}
                        {community.verification_status}
                    </p>
                    {community.description && (
                        <p className="max-w-3xl text-muted-foreground">
                            {community.description}
                        </p>
                    )}
                </header>

                {announcements.length > 0 && (
                    <ul className="space-y-2">
                        {announcements.map((announcement) => (
                            <li
                                key={announcement.id}
                                className="rounded-lg border p-3"
                            >
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
                            </li>
                        ))}
                    </ul>
                )}

                <section aria-labelledby="population" className="space-y-3">
                    <h2 id="population" className="text-xl font-semibold">
                        Population and housing
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {population
                            ? `Approved results from the ${population.campaign.name} (reference date ${population.campaign.reference_date}).`
                            : 'This community has no finalised census results yet. Figures appear only after enumeration has been verified and approved.'}
                    </p>
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-5">
                        <StatTile
                            label="Population"
                            value={
                                population?.is_enumerated
                                    ? population.approved_occupants
                                    : null
                            }
                            emptyLabel="Not enumerated"
                        />
                        <StatTile
                            label="Households"
                            value={
                                population?.is_enumerated
                                    ? population.approved_households
                                    : null
                            }
                            emptyLabel="Not enumerated"
                        />
                        <StatTile
                            label="Houses"
                            value={
                                population?.is_enumerated
                                    ? population.approved_houses
                                    : null
                            }
                            emptyLabel="Not enumerated"
                        />
                        <StatTile
                            label="Average household size"
                            value={population?.average_household_size ?? null}
                            emptyLabel="—"
                        />
                        <StatTile label="Blocks" value={community.blocks} />
                    </div>
                </section>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Services and facilities</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {groups.map((group) => {
                                const items = facilities.filter((facility) =>
                                    group.categories.includes(
                                        facility.category,
                                    ),
                                );

                                return (
                                    <div key={group.label}>
                                        <div className="text-sm font-medium">
                                            {group.label}{' '}
                                            <span className="text-muted-foreground">
                                                ({items.length})
                                            </span>
                                        </div>
                                        {items.length === 0 ? (
                                            <p className="text-xs text-muted-foreground">
                                                None verified on record.
                                            </p>
                                        ) : (
                                            <ul className="mt-1 space-y-1 text-sm">
                                                {items.map((facility) => (
                                                    <li key={facility.name}>
                                                        {facility.name}{' '}
                                                        <span className="text-xs text-muted-foreground">
                                                            · {facility.type} ·{' '}
                                                            {facility.ownership}{' '}
                                                            ·{' '}
                                                            {
                                                                facility.operating_status
                                                            }
                                                        </span>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Infrastructure</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {infrastructure.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No verified infrastructure records
                                    published.
                                </p>
                            ) : (
                                <ul className="space-y-2 text-sm">
                                    {infrastructure.map((asset) => (
                                        <li
                                            key={asset.name}
                                            className="flex justify-between gap-2 border-b pb-2 last:border-0"
                                        >
                                            <span>
                                                {asset.name}{' '}
                                                <span className="text-xs text-muted-foreground">
                                                    · {asset.category}
                                                </span>
                                            </span>
                                            <span className="text-muted-foreground">
                                                {asset.condition},{' '}
                                                {asset.availability.toLowerCase()}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Leadership</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {leaders.current.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No verified leaders published.
                                </p>
                            ) : (
                                <ul className="space-y-2 text-sm">
                                    {leaders.current.map((leader) => (
                                        <li
                                            key={`${leader.position}-${leader.name}`}
                                        >
                                            <span className="font-medium">
                                                {leader.name}
                                            </span>{' '}
                                            — {leader.position}
                                            {leader.block &&
                                                ` (${leader.block})`}
                                            <div className="text-xs text-muted-foreground">
                                                Since {leader.starts_on} ·{' '}
                                                {leader.selection_method}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {leaders.former.length > 0 && (
                                <details className="text-sm">
                                    <summary className="cursor-pointer text-muted-foreground">
                                        Former leaders ({leaders.former.length})
                                    </summary>
                                    <ul className="mt-2 space-y-1">
                                        {leaders.former.map((leader) => (
                                            <li
                                                key={`${leader.position}-${leader.name}-${leader.starts_on}`}
                                            >
                                                {leader.name} —{' '}
                                                {leader.position},{' '}
                                                {leader.starts_on} –{' '}
                                                {leader.ends_on}
                                            </li>
                                        ))}
                                    </ul>
                                </details>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Events and contact</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm">
                            {events.length === 0 ? (
                                <p className="text-muted-foreground">
                                    No upcoming events.
                                </p>
                            ) : (
                                <ul className="space-y-2">
                                    {events.map((event) => (
                                        <li
                                            key={event.slug}
                                            className="flex gap-2"
                                        >
                                            <Calendar className="mt-0.5 size-4 text-muted-foreground" />
                                            <span>
                                                <Link
                                                    href={eventShow(event.slug)}
                                                    className="font-medium hover:underline"
                                                >
                                                    {event.title}
                                                </Link>
                                                <span className="block text-xs text-muted-foreground">
                                                    {new Date(
                                                        event.starts_at,
                                                    ).toLocaleString()}
                                                </span>
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {(community.public_email ||
                                community.public_phone) && (
                                <div className="space-y-1 border-t pt-3">
                                    {community.public_email && (
                                        <a
                                            href={`mailto:${community.public_email}`}
                                            className="flex items-center gap-2 hover:underline"
                                        >
                                            <Mail className="size-4" />{' '}
                                            {community.public_email}
                                        </a>
                                    )}
                                    {community.public_phone && (
                                        <a
                                            href={`tel:${community.public_phone}`}
                                            className="flex items-center gap-2 hover:underline"
                                        >
                                            <Phone className="size-4" />{' '}
                                            {community.public_phone}
                                        </a>
                                    )}
                                </div>
                            )}
                            {community.location && (
                                <a
                                    className="block underline underline-offset-4"
                                    href={`https://www.openstreetmap.org/?mlat=${community.location.latitude}&mlon=${community.location.longitude}#map=15/${community.location.latitude}/${community.location.longitude}`}
                                    target="_blank"
                                    rel="noreferrer noopener"
                                >
                                    Directions (verified community location)
                                </a>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {community.history && (
                    <section className="space-y-2">
                        <h2 className="text-xl font-semibold">History</h2>
                        <p className="max-w-3xl whitespace-pre-line text-muted-foreground">
                            {community.history}
                        </p>
                    </section>
                )}

                {gallery.length > 0 && (
                    <section aria-labelledby="gallery" className="space-y-3">
                        <h2 id="gallery" className="text-xl font-semibold">
                            Gallery
                        </h2>
                        <ul className="grid grid-cols-2 gap-3 md:grid-cols-4">
                            {gallery.map((item) => (
                                <li key={item.uuid}>
                                    <button
                                        type="button"
                                        onClick={() => setPhoto(item)}
                                        className="block w-full overflow-hidden rounded-md border"
                                    >
                                        <img
                                            src={item.thumbnail_url}
                                            alt={item.alt}
                                            className="aspect-square w-full object-cover"
                                            loading="lazy"
                                        />
                                    </button>
                                    {item.caption && (
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {item.caption}
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>

            <Dialog
                open={photo !== null}
                onOpenChange={(open) => !open && setPhoto(null)}
            >
                <DialogContent className="sm:max-w-4xl">
                    <DialogTitle>
                        {photo?.caption ?? community.name}
                    </DialogTitle>
                    {photo && (
                        <img
                            src={photo.url}
                            alt={photo.alt}
                            className="max-h-[75vh] w-full rounded-md object-contain"
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
