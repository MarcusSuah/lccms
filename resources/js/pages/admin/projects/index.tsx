import { Head, Link } from '@inertiajs/react';
import { FolderKanban, Plus } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { FilterBar } from '@/components/admin/filter-bar';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { StatusBadge } from '@/components/admin/status-badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { create, index, show } from '@/routes/admin/projects';
import type { Option, SimplePaginated } from '@/types';

type Project = {
    id: number;
    reference_code: string;
    title: string;
    category_label: string;
    status: string;
    area: string;
    progress_percent: number;
    target_end_date: string | null;
};

export default function ProjectsIndex({
    projects,
    filters,
    options,
    can,
}: {
    projects: SimplePaginated<Project>;
    filters: Record<string, string>;
    options: { categories: Option[]; statuses: Option[] };
    can: { manage: boolean };
}) {
    return (
        <>
            <Head title="Development projects" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Community development"
                    title="Development projects"
                    actions={
                        can.manage && (
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus /> New project
                                </Link>
                            </Button>
                        )
                    }
                />
                <FilterBar
                    filters={filters}
                    searchPlaceholder="Title or reference"
                    selects={[
                        {
                            name: 'status',
                            label: 'Status',
                            options: options.statuses,
                        },
                        {
                            name: 'category',
                            label: 'Category',
                            options: options.categories,
                        },
                    ]}
                />
                {projects.data.length === 0 ? (
                    <EmptyState
                        icon={FolderKanban}
                        title="No projects recorded"
                    />
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Project</TableHead>
                                    <TableHead>Area</TableHead>
                                    <TableHead className="text-right">
                                        Progress
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {projects.data.map((project) => (
                                    <TableRow key={project.id}>
                                        <TableCell>
                                            <Link
                                                href={show(project.id)}
                                                className="font-medium hover:underline"
                                            >
                                                {project.title}
                                            </Link>
                                            <div className="text-xs text-muted-foreground">
                                                {project.reference_code} ·{' '}
                                                {project.category_label}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {project.area}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {project.progress_percent}%
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                status={project.status}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
                <Pagination
                    links={projects.links}
                    from={projects.from}
                    to={projects.to}
                    total={projects.total}
                />
            </div>
        </>
    );
}

ProjectsIndex.layout = {
    breadcrumbs: [{ title: 'Projects', href: index() }],
};
