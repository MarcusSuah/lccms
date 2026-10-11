import { Head, Link, router } from '@inertiajs/react';
import { Copy } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { FilterBar } from '@/components/admin/filter-bar';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { StatusBadge } from '@/components/admin/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index, update } from '@/routes/admin/census/duplicates';
import { show as showHousehold } from '@/routes/admin/census/households';
import type { Option, SimplePaginated } from '@/types';

type Person = {
    id: number;
    reference_code: string;
    household_id: number;
    full_name: string | null;
    date_of_birth: string | null;
    age_years: number | null;
    sex: string;
    status: string;
};

type Flag = {
    id: number;
    score: number;
    reasons: string[];
    status: string;
    occupant: Person;
    matched: Person;
    reviewed_by: string | null;
    review_note: string | null;
};

function PersonCard({ person }: { person: Person }) {
    return (
        <div className="space-y-1 rounded-md border p-3 text-sm">
            <div className="font-medium">
                {person.full_name ?? (
                    <span className="text-muted-foreground">Name hidden</span>
                )}
            </div>
            <div className="text-muted-foreground">
                {person.sex}
                {person.date_of_birth && ` · born ${person.date_of_birth}`}
                {person.age_years !== null && ` · ${person.age_years} years`}
            </div>
            <Link
                href={showHousehold(person.household_id)}
                className="font-mono text-xs hover:underline"
            >
                {person.reference_code}
            </Link>{' '}
            <StatusBadge status={person.status} />
        </div>
    );
}

export default function Duplicates({
    flags,
    filters,
    statusOptions,
}: {
    flags: SimplePaginated<Flag>;
    filters: { status: string };
    statusOptions: Option[];
}) {
    const resolve = (id: number, status: string) => {
        const note = window.prompt('Note for the review record');

        if (note) {
            router.patch(
                update.url(id),
                { status, note },
                { preserveScroll: true },
            );
        }
    };

    return (
        <>
            <Head title="Possible duplicates" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Census"
                    title="Possible duplicates"
                    description="Records that may describe the same person in this campaign. Nothing is merged or deleted automatically: confirm each pair, then return or correct the affected household."
                />
                <FilterBar
                    filters={filters}
                    selects={[
                        {
                            name: 'status',
                            label: 'Status',
                            options: statusOptions,
                        },
                    ]}
                />
                {flags.data.length === 0 ? (
                    <EmptyState icon={Copy} title="Nothing to review" />
                ) : (
                    <div className="grid gap-4">
                        {flags.data.map((flag) => (
                            <Card key={flag.id}>
                                <CardContent className="space-y-3 pt-6">
                                    <div className="flex flex-wrap items-center gap-2 text-sm">
                                        <StatusBadge status={flag.status} />
                                        <span className="font-medium">
                                            Match score {flag.score}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {flag.reasons.join(' · ')}
                                        </span>
                                    </div>
                                    <div className="grid gap-3 md:grid-cols-2">
                                        <PersonCard person={flag.occupant} />
                                        <PersonCard person={flag.matched} />
                                    </div>
                                    {flag.status === 'open' ? (
                                        <div className="flex gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    resolve(
                                                        flag.id,
                                                        'confirmed_distinct',
                                                    )
                                                }
                                            >
                                                Different people
                                            </Button>
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    resolve(
                                                        flag.id,
                                                        'confirmed_duplicate',
                                                    )
                                                }
                                            >
                                                Same person
                                            </Button>
                                        </div>
                                    ) : (
                                        <p className="text-xs text-muted-foreground">
                                            Reviewed by {flag.reviewed_by}:{' '}
                                            {flag.review_note}
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
                <Pagination
                    links={flags.links}
                    from={flags.from}
                    to={flags.to}
                    total={flags.total}
                />
            </div>
        </>
    );
}

Duplicates.layout = {
    breadcrumbs: [{ title: 'Possible duplicates', href: index() }],
};
