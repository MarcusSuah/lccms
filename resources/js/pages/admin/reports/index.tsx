import { Head, Link } from '@inertiajs/react';
import { Download, FileSpreadsheet, ShieldAlert } from 'lucide-react';
import { PageHeader } from '@/components/admin/page-header';
import { StatusBadge } from '@/components/admin/status-badge';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { download } from '@/routes/admin/exports';
import { index, show } from '@/routes/admin/reports';
import type { ExportJobSummary, ReportSummary } from '@/types';

export default function ReportsIndex({
    reports,
    exports,
}: {
    reports: ReportSummary[];
    exports: ExportJobSummary[];
}) {
    const categories = [...new Set(reports.map((report) => report.category))];

    return (
        <>
            <Head title="Reports" />
            <div className="flex flex-1 flex-col gap-8 p-4 md:p-6">
                <PageHeader
                    title="Reports"
                    description="Every report is limited to the areas and records you can access. Exports are generated in the background, kept for a short time, and recorded."
                />

                {categories.map((category) => (
                    <section key={category} className="space-y-3">
                        <h2 className="text-sm font-medium text-muted-foreground">
                            {category}
                        </h2>
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            {reports
                                .filter(
                                    (report) => report.category === category,
                                )
                                .map((report) => (
                                    <Link
                                        key={report.key}
                                        href={show(report.key)}
                                        className="rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        <Card className="h-full transition-colors hover:bg-accent/50">
                                            <CardHeader>
                                                <CardTitle className="flex items-center gap-2 text-base">
                                                    <FileSpreadsheet
                                                        className="size-4 text-muted-foreground"
                                                        aria-hidden
                                                    />
                                                    {report.title}
                                                    {report.personal_data && (
                                                        <Badge
                                                            variant="outline"
                                                            className="ml-auto gap-1 border-amber-300 text-amber-900 dark:border-amber-800 dark:text-amber-300"
                                                        >
                                                            <ShieldAlert className="size-3" />
                                                            Personal data
                                                        </Badge>
                                                    )}
                                                </CardTitle>
                                                <CardDescription>
                                                    {report.description}
                                                </CardDescription>
                                            </CardHeader>
                                        </Card>
                                    </Link>
                                ))}
                        </div>
                    </section>
                ))}

                {exports.length > 0 && (
                    <section className="space-y-3">
                        <h2 className="text-sm font-medium text-muted-foreground">
                            Your recent exports
                        </h2>
                        <ul className="divide-y rounded-lg border">
                            {exports.map((item) => (
                                <li
                                    key={item.uuid}
                                    className="flex flex-wrap items-center gap-3 px-4 py-2.5 text-sm"
                                >
                                    <span className="font-medium">
                                        {item.report_title}
                                    </span>
                                    <span className="text-muted-foreground uppercase">
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
                                    {item.is_downloadable && (
                                        <a
                                            href={download.url(item.uuid)}
                                            className="ml-auto inline-flex items-center gap-1 text-primary hover:underline"
                                        >
                                            <Download className="size-3.5" />
                                            Download
                                        </a>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [{ title: 'Reports', href: index() }],
};
