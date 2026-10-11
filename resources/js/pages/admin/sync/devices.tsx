import { Head, router } from '@inertiajs/react';
import { Smartphone } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
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
import { destroy, index } from '@/routes/admin/sync/devices';
import type { SimplePaginated } from '@/types';

type DeviceRow = {
    id: number;
    label: string;
    user: { id: number; name: string; email: string } | null;
    platform: string | null;
    last_seen_at: string | null;
    last_sync_at: string | null;
    offline_expires_at: string | null;
    revoked_at: string | null;
    revoke_reason: string | null;
    batches_count: number;
    last_summary: Record<string, number> | null;
};

const when = (value: string | null) =>
    value ? new Date(value).toLocaleString() : '—';

export default function Devices({
    devices,
}: {
    devices: SimplePaginated<DeviceRow>;
}) {
    return (
        <>
            <Head title="Field devices" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Field operations"
                    title="Field devices"
                    description="Revoke a lost, stolen or retired device immediately. Its token stops working at once and it erases its local data the next time it connects."
                />
                {devices.data.length === 0 ? (
                    <EmptyState
                        icon={Smartphone}
                        title="No registered devices"
                    />
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Device</TableHead>
                                    <TableHead>Enumerator</TableHead>
                                    <TableHead>Last contact</TableHead>
                                    <TableHead>Last sync result</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="sr-only">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {devices.data.map((device) => (
                                    <TableRow key={device.id}>
                                        <TableCell>
                                            <div className="font-medium">
                                                {device.label}
                                            </div>
                                            <div className="max-w-56 truncate text-xs text-muted-foreground">
                                                {device.platform}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {device.user?.name}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {when(device.last_seen_at)}
                                            <div className="text-xs text-muted-foreground">
                                                Synced{' '}
                                                {when(device.last_sync_at)}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {device.last_summary
                                                ? Object.entries(
                                                      device.last_summary,
                                                  )
                                                      .map(
                                                          ([status, count]) =>
                                                              `${count} ${status}`,
                                                      )
                                                      .join(', ')
                                                : '—'}
                                        </TableCell>
                                        <TableCell>
                                            {device.revoked_at ? (
                                                <span
                                                    title={
                                                        device.revoke_reason ??
                                                        ''
                                                    }
                                                >
                                                    <StatusBadge
                                                        status="archived"
                                                        label="Revoked"
                                                    />
                                                </span>
                                            ) : (
                                                <StatusBadge status="active" />
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {!device.revoked_at && (
                                                <Button
                                                    size="sm"
                                                    variant="destructive"
                                                    onClick={() => {
                                                        const reason =
                                                            window.prompt(
                                                                'Why is this device being revoked?',
                                                            );

                                                        if (reason) {
                                                            router.delete(
                                                                destroy.url(
                                                                    device.id,
                                                                ),
                                                                {
                                                                    data: {
                                                                        reason,
                                                                    },
                                                                    preserveScroll: true,
                                                                },
                                                            );
                                                        }
                                                    }}
                                                >
                                                    Revoke
                                                </Button>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
                <Pagination
                    links={devices.links}
                    from={devices.from}
                    to={devices.to}
                    total={devices.total}
                />
            </div>
        </>
    );
}

Devices.layout = {
    breadcrumbs: [{ title: 'Field devices', href: index() }],
};
