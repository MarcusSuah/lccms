import { Head, Link, router, useForm } from '@inertiajs/react';
import { CheckCircle2, Pencil } from 'lucide-react';
import { FormField } from '@/components/admin/form-field';
import { PageHeader } from '@/components/admin/page-header';
import { StatusBadge } from '@/components/admin/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { edit, index } from '@/routes/admin/projects';
import {
    store as storeMilestone,
    update as updateMilestone,
} from '@/routes/admin/projects/milestones';
import { store as storeReport } from '@/routes/admin/projects/reports';

type Project = {
    id: number;
    reference_code: string;
    title: string;
    description: string | null;
    category_label: string;
    status: string;
    area: string;
    start_date: string | null;
    target_end_date: string | null;
    actual_end_date: string | null;
    sponsor: string | null;
    responsible_organization: string | null;
    budget_amount: string | null;
    budget_currency: string | null;
    budget_is_public: boolean;
    progress_percent: number;
    verification_status: string;
    milestones: {
        id: number;
        title: string;
        due_on: string | null;
        completed_on: string | null;
        notes: string | null;
    }[];
    reports: {
        id: number;
        reported_on: string;
        progress_percent: number;
        summary: string;
        reporter: string | null;
    }[];
};

export default function ProjectShow({
    project,
    can,
}: {
    project: Project;
    can: { manage: boolean };
}) {
    const milestone = useForm({ title: '', due_on: '' });
    const report = useForm({
        reported_on: new Date().toISOString().slice(0, 10),
        progress_percent: project.progress_percent,
        summary: '',
    });

    return (
        <>
            <Head title={project.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow={`${project.category_label} · ${project.reference_code}`}
                    title={project.title}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            <StatusBadge status={project.status} />
                            <StatusBadge status={project.verification_status} />
                            {project.area}
                        </span>
                    }
                    actions={
                        can.manage && (
                            <Button asChild variant="outline">
                                <Link href={edit(project.id)}>
                                    <Pencil /> Edit
                                </Link>
                            </Button>
                        )
                    }
                />
                <div className="grid gap-6 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Overview</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <div>
                                <div className="mb-1 flex justify-between text-xs text-muted-foreground">
                                    <span>Progress</span>
                                    <span>{project.progress_percent}%</span>
                                </div>
                                <div
                                    className="h-2 rounded-full bg-muted"
                                    role="progressbar"
                                    aria-valuenow={project.progress_percent}
                                    aria-valuemin={0}
                                    aria-valuemax={100}
                                >
                                    <div
                                        className="h-2 rounded-full bg-primary"
                                        style={{
                                            width: `${project.progress_percent}%`,
                                        }}
                                    />
                                </div>
                            </div>
                            {project.description && (
                                <p className="whitespace-pre-line">
                                    {project.description}
                                </p>
                            )}
                            <dl className="grid gap-1">
                                <div>
                                    Timeline: {project.start_date ?? '?'} →{' '}
                                    {project.actual_end_date ??
                                        project.target_end_date ??
                                        '?'}
                                </div>
                                {project.sponsor && (
                                    <div>Sponsor: {project.sponsor}</div>
                                )}
                                {project.responsible_organization && (
                                    <div>
                                        Responsible:{' '}
                                        {project.responsible_organization}
                                    </div>
                                )}
                                {project.budget_amount && (
                                    <div>
                                        Budget: {project.budget_currency}{' '}
                                        {Number(
                                            project.budget_amount,
                                        ).toLocaleString()}{' '}
                                        {project.budget_is_public
                                            ? '(public)'
                                            : '(staff only)'}
                                    </div>
                                )}
                            </dl>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Milestones</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <ul className="space-y-2 text-sm">
                                {project.milestones.length === 0 && (
                                    <li className="text-muted-foreground">
                                        No milestones yet.
                                    </li>
                                )}
                                {project.milestones.map((item) => (
                                    <li
                                        key={item.id}
                                        className="flex items-center justify-between gap-2"
                                    >
                                        <span>
                                            <span
                                                className={
                                                    item.completed_on
                                                        ? 'line-through'
                                                        : ''
                                                }
                                            >
                                                {item.title}
                                            </span>
                                            <span className="block text-xs text-muted-foreground">
                                                {item.completed_on
                                                    ? `Completed ${item.completed_on}`
                                                    : item.due_on
                                                      ? `Due ${item.due_on}`
                                                      : 'No due date'}
                                            </span>
                                        </span>
                                        {can.manage && !item.completed_on && (
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                aria-label={`Mark ${item.title} complete`}
                                                onClick={() =>
                                                    router.patch(
                                                        updateMilestone.url({
                                                            project: project.id,
                                                            milestone: item.id,
                                                        }),
                                                        {
                                                            completed_on:
                                                                new Date()
                                                                    .toISOString()
                                                                    .slice(
                                                                        0,
                                                                        10,
                                                                    ),
                                                        },
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <CheckCircle2 />
                                            </Button>
                                        )}
                                    </li>
                                ))}
                            </ul>
                            {can.manage && (
                                <form
                                    className="space-y-2 border-t pt-3"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        milestone.transform((data) => ({
                                            ...data,
                                            due_on: data.due_on || null,
                                        }));
                                        milestone.submit(
                                            storeMilestone(project.id),
                                            {
                                                preserveScroll: true,
                                                onSuccess: () =>
                                                    milestone.reset(),
                                            },
                                        );
                                    }}
                                >
                                    <Input
                                        aria-label="Milestone"
                                        placeholder="New milestone"
                                        value={milestone.data.title}
                                        onChange={(event) =>
                                            milestone.setData(
                                                'title',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <Input
                                        aria-label="Due on"
                                        type="date"
                                        value={milestone.data.due_on}
                                        onChange={(event) =>
                                            milestone.setData(
                                                'due_on',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <Button
                                        size="sm"
                                        type="submit"
                                        disabled={!milestone.data.title}
                                    >
                                        Add milestone
                                    </Button>
                                </form>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Progress reports</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {can.manage && (
                                <form
                                    className="space-y-2 border-b pb-3"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        report.submit(storeReport(project.id), {
                                            preserveScroll: true,
                                            onSuccess: () =>
                                                report.reset('summary'),
                                        });
                                    }}
                                >
                                    <div className="grid grid-cols-2 gap-2">
                                        <Input
                                            aria-label="Report date"
                                            type="date"
                                            value={report.data.reported_on}
                                            onChange={(event) =>
                                                report.setData(
                                                    'reported_on',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <Input
                                            aria-label="Progress percent"
                                            type="number"
                                            min={0}
                                            max={100}
                                            value={report.data.progress_percent}
                                            onChange={(event) =>
                                                report.setData(
                                                    'progress_percent',
                                                    Number(event.target.value),
                                                )
                                            }
                                        />
                                    </div>
                                    <FormField
                                        label="Summary"
                                        htmlFor="report-summary"
                                        error={report.errors.summary}
                                    >
                                        <Textarea
                                            id="report-summary"
                                            value={report.data.summary}
                                            onChange={(event) =>
                                                report.setData(
                                                    'summary',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </FormField>
                                    <Button
                                        size="sm"
                                        type="submit"
                                        disabled={!report.data.summary}
                                    >
                                        Add report
                                    </Button>
                                </form>
                            )}
                            <ul className="space-y-3 text-sm">
                                {project.reports.length === 0 && (
                                    <li className="text-muted-foreground">
                                        No progress reports yet.
                                    </li>
                                )}
                                {project.reports.map((item) => (
                                    <li key={item.id}>
                                        <div className="font-medium">
                                            {item.reported_on} ·{' '}
                                            {item.progress_percent}%
                                        </div>
                                        <p>{item.summary}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {item.reporter}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

ProjectShow.layout = {
    breadcrumbs: [{ title: 'Projects', href: index() }],
};
