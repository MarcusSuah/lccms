import { Head, router } from '@inertiajs/react';
import {
    CheckCircle2,
    CircleDashed,
    CircleHelp,
    MinusCircle,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { FilterBar } from '@/components/admin/filter-bar';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { index, update } from '@/routes/admin/needs-assessment';
import type { Option, SimplePaginated } from '@/types';

type Row = {
    id: number;
    name: string;
    reference_code: string;
    location: string;
    services: Record<string, string>;
};

const cells: Record<
    string,
    { icon: typeof CheckCircle2; label: string; className: string }
> = {
    present: {
        icon: CheckCircle2,
        label: 'Present',
        className: 'text-emerald-700 dark:text-emerald-400',
    },
    gap: {
        icon: XCircle,
        label: 'Not working',
        className: 'text-red-700 dark:text-red-400',
    },
    verified_absent: {
        icon: MinusCircle,
        label: 'Verified absent',
        className: 'text-red-700 dark:text-red-400',
    },
    unverified: {
        icon: CircleHelp,
        label: 'Recorded, unverified',
        className: 'text-amber-700 dark:text-amber-400',
    },
    not_assessed: {
        icon: CircleDashed,
        label: 'Not assessed',
        className: 'text-muted-foreground',
    },
};

export default function NeedsAssessmentPage({
    rows,
    services,
    filters,
    assessmentOptions,
    can,
}: {
    rows: SimplePaginated<Row>;
    services: Option[];
    filters: { search: string; gaps_only: boolean };
    assessmentOptions: Option[];
    can: { assess: boolean };
}) {
    const [editing, setEditing] = useState<{
        row: Row;
        service: Option;
    } | null>(null);
    const [status, setStatus] = useState('present');
    const [assessedOn, setAssessedOn] = useState(
        new Date().toISOString().slice(0, 10),
    );
    const [notes, setNotes] = useState('');

    return (
        <>
            <Head title="Needs assessment" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Community services"
                    title="Needs assessment"
                    description="Service availability per community from the facility and infrastructure registers and verified assessments. “Not assessed” means unknown, never “none”."
                />
                <div className="flex flex-wrap items-center gap-4">
                    <FilterBar
                        filters={{
                            search: filters.search,
                            gaps_only: filters.gaps_only ? 1 : '',
                        }}
                        searchPlaceholder="Community"
                    />
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={filters.gaps_only}
                            onCheckedChange={(checked) =>
                                router.get(
                                    index.url({
                                        query: {
                                            search: filters.search || undefined,
                                            gaps_only:
                                                checked === true
                                                    ? 1
                                                    : undefined,
                                        },
                                    }),
                                    {},
                                    { preserveState: true },
                                )
                            }
                        />
                        Only communities with verified gaps
                    </label>
                </div>
                <ul
                    className="flex flex-wrap gap-4 text-xs"
                    aria-label="Legend"
                >
                    {Object.entries(cells).map(([key, cell]) => (
                        <li
                            key={key}
                            className={cn(
                                'flex items-center gap-1',
                                cell.className,
                            )}
                        >
                            <cell.icon className="size-4" aria-hidden />{' '}
                            {cell.label}
                        </li>
                    ))}
                </ul>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/40 text-left">
                                <th className="p-2 font-medium">Community</th>
                                {services.map((service) => (
                                    <th
                                        key={service.value}
                                        className="p-2 text-center text-xs font-medium"
                                    >
                                        {service.label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.data.map((row) => (
                                <tr
                                    key={row.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="p-2">
                                        <div className="font-medium">
                                            {row.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {row.location}
                                        </div>
                                    </td>
                                    {services.map((service) => {
                                        const cell =
                                            cells[
                                                row.services[service.value] ??
                                                    'not_assessed'
                                            ];

                                        return (
                                            <td
                                                key={service.value}
                                                className="p-2 text-center"
                                            >
                                                <button
                                                    type="button"
                                                    disabled={!can.assess}
                                                    title={`${service.label}: ${cell.label}`}
                                                    aria-label={`${row.name}, ${service.label}: ${cell.label}`}
                                                    onClick={() =>
                                                        setEditing({
                                                            row,
                                                            service,
                                                        })
                                                    }
                                                    className={cn(
                                                        'inline-flex rounded p-1 enabled:hover:bg-accent',
                                                        cell.className,
                                                    )}
                                                >
                                                    <cell.icon className="size-5" />
                                                </button>
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Pagination
                    links={rows.links}
                    from={rows.from}
                    to={rows.to}
                    total={rows.total}
                />
            </div>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {editing?.service.label} in {editing?.row.name}
                        </DialogTitle>
                    </DialogHeader>
                    <div className="space-y-3">
                        <NativeSelect
                            aria-label="Assessment"
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                        >
                            {assessmentOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </NativeSelect>
                        <Input
                            type="date"
                            aria-label="Assessed on"
                            value={assessedOn}
                            onChange={(event) =>
                                setAssessedOn(event.target.value)
                            }
                        />
                        <Textarea
                            aria-label="Notes"
                            placeholder="How was this verified?"
                            value={notes}
                            onChange={(event) => setNotes(event.target.value)}
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            onClick={() => {
                                if (!editing) return;
                                router.put(
                                    update.url(editing.row.id),
                                    {
                                        category: editing.service.value,
                                        status,
                                        assessed_on: assessedOn,
                                        notes,
                                    },
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => setEditing(null),
                                    },
                                );
                            }}
                        >
                            Save assessment
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

NeedsAssessmentPage.layout = {
    breadcrumbs: [{ title: 'Needs assessment', href: index() }],
};
