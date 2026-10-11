import { Head, router } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { PageHeader } from '@/components/admin/page-header';
import { StatusBadge } from '@/components/admin/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import { index, update } from '@/routes/admin/geography/imports';

type PreviewRow = {
    row: number;
    level: string;
    reference_code: string;
    name: string;
    parent_reference_code: string;
    has_error?: boolean;
};

type Issue = {
    row_number: number;
    column: string | null;
    message: string;
    severity: 'error' | 'warning';
};

export default function ImportShow({
    import: summary,
    issues,
}: {
    import: {
        uuid: string;
        original_filename: string;
        status: string;
        total_rows: number;
        valid_rows: number;
        invalid_rows: number;
        imported_rows: number;
        preview: PreviewRow[];
    };
    issues: Issue[];
}) {
    const act = (action: 'commit' | 'discard') =>
        router.patch(update.url(summary.uuid), { action });

    return (
        <>
            <Head title={summary.original_filename} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Geography import"
                    title={summary.original_filename}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            <StatusBadge status={summary.status} />
                            {summary.total_rows} rows · {summary.valid_rows}{' '}
                            valid · {summary.invalid_rows} with errors
                            {summary.status === 'committed' &&
                                ` · ${summary.imported_rows} imported`}
                        </span>
                    }
                    actions={
                        summary.status === 'validated' ? (
                            <>
                                <Button
                                    variant="ghost"
                                    onClick={() => act('discard')}
                                >
                                    Discard
                                </Button>
                                <Button onClick={() => act('commit')}>
                                    <CheckCircle2 /> Commit {summary.total_rows}{' '}
                                    records
                                </Button>
                            </>
                        ) : (
                            summary.status !== 'committed' &&
                            summary.status !== 'discarded' && (
                                <Button
                                    variant="ghost"
                                    onClick={() => act('discard')}
                                >
                                    Discard
                                </Button>
                            )
                        )
                    }
                />

                {issues.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Validation findings</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="space-y-1 text-sm">
                                {issues.map((issue, position) => (
                                    <li
                                        key={position}
                                        className={cn(
                                            issue.severity === 'error'
                                                ? 'text-destructive'
                                                : 'text-amber-700 dark:text-amber-400',
                                        )}
                                    >
                                        Row {issue.row_number}
                                        {issue.column &&
                                            ` · ${issue.column}`}:{' '}
                                        {issue.message}
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Preview (first 100 rows)</CardTitle>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Row</TableHead>
                                    <TableHead>Level</TableHead>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Reference</TableHead>
                                    <TableHead>Parent</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {summary.preview.map((row) => (
                                    <TableRow
                                        key={row.row}
                                        className={cn(
                                            row.has_error &&
                                                'bg-red-50 dark:bg-red-950/40',
                                        )}
                                    >
                                        <TableCell className="tabular-nums">
                                            {row.row}
                                        </TableCell>
                                        <TableCell className="capitalize">
                                            {row.level}
                                        </TableCell>
                                        <TableCell>{row.name}</TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {row.reference_code || 'generated'}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {row.parent_reference_code || '—'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ImportShow.layout = {
    breadcrumbs: [{ title: 'Geography imports', href: index() }],
};
