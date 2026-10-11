import { Head, Link } from '@inertiajs/react';
import { Plus, ShieldCheck, Users } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { FilterBar } from '@/components/admin/filter-bar';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { StatusBadge } from '@/components/admin/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { create, edit, index } from '@/routes/admin/users';
import type { Option, Paginated, StaffUser } from '@/types';

export default function UsersIndex({
    users,
    filters,
    roleOptions,
    can,
}: {
    users: Paginated<StaffUser>;
    filters: { search: string; role: string };
    roleOptions: Option[];
    can: { create: boolean };
}) {
    return (
        <>
            <Head title="Staff accounts" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Staff accounts"
                    description="Accounts are created by administrators. Each account has roles (what it may do) and geographic areas (where it may do it)."
                    actions={
                        can.create && (
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus /> New account
                                </Link>
                            </Button>
                        )
                    }
                />

                <FilterBar
                    filters={filters}
                    searchPlaceholder="Name or email"
                    selects={[
                        { name: 'role', label: 'Role', options: roleOptions },
                    ]}
                />

                {users.data.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title="No staff accounts found"
                        description="Only accounts assigned inside your areas are listed."
                    />
                ) : (
                    <div className="overflow-hidden rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Roles</TableHead>
                                    <TableHead className="hidden lg:table-cell">
                                        Areas
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="sr-only">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {users.data.map((user) => (
                                    <TableRow key={user.id}>
                                        <TableCell>
                                            <div className="font-medium">
                                                {user.name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {user.email}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                {user.roles?.map((role) => (
                                                    <Badge
                                                        key={role.value}
                                                        variant="secondary"
                                                    >
                                                        {role.label}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </TableCell>
                                        <TableCell className="hidden max-w-xs text-sm text-muted-foreground lg:table-cell">
                                            {user.scopes?.length
                                                ? user.scopes
                                                      .map(
                                                          (scope) =>
                                                              scope.label,
                                                      )
                                                      .join('; ')
                                                : 'National'}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap items-center gap-1">
                                                <StatusBadge
                                                    status={
                                                        user.is_active
                                                            ? 'active'
                                                            : 'inactive'
                                                    }
                                                />
                                                {!user.email_verified && (
                                                    <StatusBadge
                                                        status="pending"
                                                        label="Invited"
                                                    />
                                                )}
                                                {user.two_factor_enabled && (
                                                    <ShieldCheck
                                                        className="size-4 text-emerald-600"
                                                        aria-label="Two-factor enabled"
                                                    />
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="sm"
                                            >
                                                <Link href={edit(user.id)}>
                                                    Edit
                                                </Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <Pagination
                    links={users.meta.links}
                    from={users.meta.from}
                    to={users.meta.to}
                    total={users.meta.total}
                />
            </div>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Staff accounts', href: index() }],
};
