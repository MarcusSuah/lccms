import { Head, router, useForm, usePoll } from '@inertiajs/react';
import { Download, ShieldAlert, X } from 'lucide-react';
import { useEffect } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import { FormField } from '@/components/admin/form-field';
import { PageHeader } from '@/components/admin/page-header';
import { StatusBadge } from '@/components/admin/status-badge';
import { GeoAreaPicker } from '@/components/geo/geo-area-picker';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { download } from '@/routes/admin/exports';
import { index, show } from '@/routes/admin/reports';
import { store as storeExport } from '@/routes/admin/reports/exports';
import type {
    ExportFormat,
    ExportJobSummary,
    GeoLevel,
    Option,
    ReportColumn,
    ReportFilters,
    ReportRow,
    ReportSummary,
} from '@/types';

type CampaignOption = {
    id: number;
    name: string;
    reference_code: string;
    status: string;
};

const formatLabels: Record<ExportFormat, string> = {
    csv: 'CSV',
    xlsx: 'Excel (.xlsx)',
    pdf: 'PDF',
};

const numberFormat = new Intl.NumberFormat();

function formatCell(value: ReportRow[string], numeric: boolean): string {
    if (value === null || value === '') {
        return '—';
    }

    if (numeric && typeof value === 'number') {
        return numberFormat.format(value);
    }

    return String(value);
}

export default function ReportShow({
    report,
    filters,
    filterSummary,
    campaigns,
    levels,
    columns,
    rows,
    truncated,
    previewLimit,
    formats,
    exports,
    hasPendingExports,
}: {
    report: ReportSummary;
    filters: ReportFilters;
    filterSummary: Record<string, string>;
    campaigns: CampaignOption[];
    levels: Option<GeoLevel>[];
    columns: ReportColumn[];
    rows: ReportRow[];
    truncated: boolean;
    previewLimit: number;
    formats: ExportFormat[];
    exports: ExportJobSummary[];
    hasPendingExports: boolean;
}) {
    const spec = report.filters;

    const apply = (
        changes: Partial<Record<keyof ReportFilters, string | number | null>>,
    ) => {
        const query: Record<string, string | number> = {};

        for (const [key, value] of Object.entries({ ...filters, ...changes })) {
            if (value !== null && value !== undefined && value !== '') {
                query[key] = value;
            }
        }

        router.get(show.url(report.key), query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const exportForm = useForm<{
        format: ExportFormat;
        purpose: string;
        filters: ReportFilters;
    }>({
        format: formats[0],
        purpose: '',
        filters,
    });

    const { start, stop } = usePoll(
        3000,
        { only: ['exports', 'hasPendingExports'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (hasPendingExports) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [hasPendingExports, start, stop]);

    return (
        <>
            <Head title={report.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow={report.category}
                    title={report.title}
                    description={report.description}
                />

                {report.personal_data && (
                    <div
                        role="note"
                        className="flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
                    >
                        <ShieldAlert className="mt-0.5 size-4 shrink-0" />
                        <p>
                            This report lists identifiable people. Viewing it is
                            recorded. Use it only for census work and do not
                            share it beyond the people who need it.
                        </p>
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Filters</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {'campaign' in spec && (
                                <div className="grid gap-1.5">
                                    <Label htmlFor="filter-campaign">
                                        Campaign
                                    </Label>
                                    <NativeSelect
                                        id="filter-campaign"
                                        value={filters.campaign ?? ''}
                                        onChange={(event) =>
                                            apply({
                                                campaign: event.target.value,
                                            })
                                        }
                                    >
                                        {campaigns.length === 0 && (
                                            <option value="">
                                                No campaigns available
                                            </option>
                                        )}
                                        {campaigns.map((campaign) => (
                                            <option
                                                key={campaign.id}
                                                value={campaign.id}
                                            >
                                                {campaign.name} (
                                                {campaign.reference_code})
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </div>
                            )}
                            {'breakdown' in spec && (
                                <div className="grid gap-1.5">
                                    <Label htmlFor="filter-breakdown">
                                        Group by
                                    </Label>
                                    <NativeSelect
                                        id="filter-breakdown"
                                        value={filters.breakdown ?? ''}
                                        onChange={(event) =>
                                            apply({
                                                breakdown: event.target.value,
                                            })
                                        }
                                    >
                                        {levels.map((level) => (
                                            <option
                                                key={level.value}
                                                value={level.value}
                                            >
                                                {level.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </div>
                            )}
                            {spec.status && (
                                <div className="grid gap-1.5">
                                    <Label htmlFor="filter-status">
                                        {spec.status.label}
                                    </Label>
                                    <NativeSelect
                                        id="filter-status"
                                        value={filters.status ?? ''}
                                        onChange={(event) =>
                                            apply({
                                                status: event.target.value,
                                            })
                                        }
                                    >
                                        <option value="">All</option>
                                        {spec.status.options.map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </div>
                            )}
                            {'period' in spec && (
                                <>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="filter-from">
                                            From
                                        </Label>
                                        <Input
                                            id="filter-from"
                                            type="date"
                                            value={filters.from ?? ''}
                                            onChange={(event) =>
                                                apply({
                                                    from: event.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="filter-to">To</Label>
                                        <Input
                                            id="filter-to"
                                            type="date"
                                            value={filters.to ?? ''}
                                            onChange={(event) =>
                                                apply({
                                                    to: event.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                </>
                            )}
                        </div>

                        {'area' in spec && (
                            <div className="space-y-2">
                                <div className="flex flex-wrap items-center gap-2 text-sm">
                                    <span className="text-muted-foreground">
                                        Area:
                                    </span>
                                    <span className="font-medium">
                                        {filterSummary.Area}
                                    </span>
                                    {filters.area_id && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                apply({
                                                    level: null,
                                                    area_id: null,
                                                    breakdown: null,
                                                })
                                            }
                                        >
                                            <X /> Clear area
                                        </Button>
                                    )}
                                </div>
                                <GeoAreaPicker
                                    maxLevel="block"
                                    idPrefix="report-area"
                                    className="lg:grid-cols-6"
                                    onChange={(selection) =>
                                        selection &&
                                        apply({
                                            level: selection.level,
                                            area_id: selection.id,
                                            breakdown: null,
                                        })
                                    }
                                />
                            </div>
                        )}
                    </CardContent>
                </Card>

                <section className="space-y-2">
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 className="font-medium">
                            {rows.length === 0
                                ? 'No rows'
                                : `${numberFormat.format(rows.length)}${truncated ? '+' : ''} rows`}
                        </h2>
                        {truncated && (
                            <p className="text-sm text-muted-foreground">
                                Showing the first{' '}
                                {numberFormat.format(previewLimit)} rows. Export
                                for the complete list.
                            </p>
                        )}
                    </div>
                    {rows.length === 0 ? (
                        <EmptyState
                            title="Nothing matches these filters"
                            description="Try another campaign or a wider area. Records outside your assigned areas are never included."
                        />
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        {columns.map((column) => (
                                            <TableHead
                                                key={column.key}
                                                className={cn(
                                                    'whitespace-nowrap',
                                                    column.numeric &&
                                                        'text-right',
                                                )}
                                            >
                                                {column.label}
                                            </TableHead>
                                        ))}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {rows.map((row, position) => (
                                        <TableRow key={position}>
                                            {columns.map((column) => (
                                                <TableCell
                                                    key={column.key}
                                                    className={cn(
                                                        column.numeric &&
                                                            'text-right tabular-nums',
                                                    )}
                                                >
                                                    {formatCell(
                                                        row[column.key],
                                                        column.numeric,
                                                    )}
                                                </TableCell>
                                            ))}
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </section>

                {report.can_export && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Export</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <form
                                className="grid gap-4 md:grid-cols-[12rem_1fr_auto] md:items-end"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    exportForm.transform((data) => ({
                                        ...data,
                                        filters,
                                    }));
                                    exportForm.submit(storeExport(report.key), {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            exportForm.reset('purpose'),
                                    });
                                }}
                            >
                                <FormField
                                    label="Format"
                                    htmlFor="export-format"
                                    error={exportForm.errors.format}
                                >
                                    <NativeSelect
                                        id="export-format"
                                        value={exportForm.data.format}
                                        onChange={(event) =>
                                            exportForm.setData(
                                                'format',
                                                event.target
                                                    .value as ExportFormat,
                                            )
                                        }
                                    >
                                        {formats.map((format) => (
                                            <option key={format} value={format}>
                                                {formatLabels[format]}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </FormField>
                                {report.personal_data ? (
                                    <FormField
                                        label="Purpose"
                                        htmlFor="export-purpose"
                                        error={exportForm.errors.purpose}
                                        required
                                        hint="Recorded with the export, e.g. “Verification visits in Block 3, week of 12 May”."
                                    >
                                        <Textarea
                                            id="export-purpose"
                                            value={exportForm.data.purpose}
                                            onChange={(event) =>
                                                exportForm.setData(
                                                    'purpose',
                                                    event.target.value,
                                                )
                                            }
                                            maxLength={500}
                                        />
                                    </FormField>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Exports use the filters above and
                                        include every matching row.
                                    </p>
                                )}
                                <Button
                                    type="submit"
                                    disabled={exportForm.processing}
                                >
                                    <Download /> Export
                                </Button>
                            </form>
                            <InputError
                                message={
                                    (
                                        exportForm.errors as Record<
                                            string,
                                            string | undefined
                                        >
                                    ).campaign ??
                                    (
                                        exportForm.errors as Record<
                                            string,
                                            string | undefined
                                        >
                                    ).area_id
                                }
                            />

                            {exports.length > 0 && (
                                <ul className="divide-y rounded-lg border text-sm">
                                    {exports.map((item) => (
                                        <li
                                            key={item.uuid}
                                            className="flex flex-wrap items-center gap-3 px-3 py-2"
                                        >
                                            <span className="w-12 font-medium uppercase">
                                                {item.format}
                                            </span>
                                            <StatusBadge status={item.status} />
                                            {item.created_at && (
                                                <span className="text-muted-foreground">
                                                    {new Date(
                                                        item.created_at,
                                                    ).toLocaleString()}
                                                </span>
                                            )}
                                            {item.row_count !== null && (
                                                <span className="text-muted-foreground">
                                                    {numberFormat.format(
                                                        item.row_count,
                                                    )}{' '}
                                                    rows
                                                </span>
                                            )}
                                            {item.error && (
                                                <span className="text-destructive">
                                                    {item.error}
                                                </span>
                                            )}
                                            {item.is_downloadable && (
                                                <a
                                                    href={download.url(
                                                        item.uuid,
                                                    )}
                                                    className="ml-auto inline-flex items-center gap-1 text-primary hover:underline"
                                                >
                                                    <Download className="size-3.5" />
                                                    Download
                                                </a>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

ReportShow.layout = {
    breadcrumbs: [{ title: 'Reports', href: index() }],
};
