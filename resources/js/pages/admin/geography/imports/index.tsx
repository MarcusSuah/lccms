import { Form, Head, Link } from '@inertiajs/react';
import { Download, FileUp } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { StatusBadge } from '@/components/admin/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index, show, store, template } from '@/routes/admin/geography/imports';
import type { SimplePaginated } from '@/types';

type ImportSummary = {
    uuid: string;
    original_filename: string;
    status: string;
    total_rows: number;
    valid_rows: number;
    invalid_rows: number;
    imported_rows: number;
    uploaded_by: string | null;
    created_at: string | null;
};

export default function ImportsIndex({
    imports,
    columns,
    maxRows,
}: {
    imports: SimplePaginated<ImportSummary>;
    columns: string[];
    maxRows: number;
}) {
    return (
        <>
            <Head title="Geography imports" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Geographic registry"
                    title="Bulk import"
                    description="Upload a CSV or Excel file. Every row is validated and previewed; nothing is saved until you commit, and the commit is all-or-nothing."
                    actions={
                        <Button asChild variant="outline">
                            <a href={template.url()}>
                                <Download /> Download template
                            </a>
                        </Button>
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Upload a file</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...store.form()}
                            encType="multipart/form-data"
                            className="flex flex-col gap-3 sm:flex-row sm:items-start"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid flex-1 gap-1">
                                        <Input
                                            type="file"
                                            name="file"
                                            accept=".csv,.xlsx"
                                            required
                                            aria-label="Import file"
                                        />
                                        <InputError message={errors.file} />
                                        <p className="text-xs text-muted-foreground">
                                            Columns: {columns.join(', ')}. Up to{' '}
                                            {maxRows.toLocaleString()} rows.
                                            Parents are matched by reference
                                            code, either already registered or
                                            earlier in the file.
                                        </p>
                                    </div>
                                    <Button type="submit" disabled={processing}>
                                        <FileUp /> Validate
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                {imports.data.length === 0 ? (
                    <EmptyState title="No imports yet" />
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>File</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Rows
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Errors
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Imported
                                    </TableHead>
                                    <TableHead>Uploaded</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {imports.data.map((item) => (
                                    <TableRow key={item.uuid}>
                                        <TableCell>
                                            <Link
                                                href={show(item.uuid)}
                                                className="font-medium hover:underline"
                                            >
                                                {item.original_filename}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge status={item.status} />
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {item.total_rows}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {item.invalid_rows}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {item.imported_rows}
                                        </TableCell>
                                        <TableCell className="text-sm text-muted-foreground">
                                            {item.uploaded_by} ·{' '}
                                            {item.created_at &&
                                                new Date(
                                                    item.created_at,
                                                ).toLocaleString()}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
                <Pagination
                    links={imports.links}
                    from={imports.from}
                    to={imports.to}
                    total={imports.total}
                />
            </div>
        </>
    );
}

ImportsIndex.layout = {
    breadcrumbs: [{ title: 'Geography imports', href: index() }],
};
