import { useLiveQuery } from 'dexie-react-hooks';
import { ChevronRight, Home, LogOut, Lock, Plus } from 'lucide-react';
import { useState } from 'react';
import { StatusBadge } from '@/components/admin/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { NativeSelect } from '@/components/ui/native-select';
import { unsyncedCount } from '@/offline/session';
import { useField, useFieldContext } from './field-context';
import { HouseForm } from './field-house';
import { SyncBadge } from './sync-badge';

export function FieldHome({
    campaigns,
}: {
    campaigns: { id: number; name: string; reference_code: string }[];
}) {
    const { pkg, ctx, chooseCampaign, navigate, session } = useField();

    const blocks = useLiveQuery(
        () => session.db.blocks.orderBy('community_id').toArray(),
        [session.db],
        [],
    );
    const houseCounts = useLiveQuery(
        async () => {
            const counts = new Map<number, number>();
            await session.db.houses.each((house) =>
                counts.set(
                    house.block_id,
                    (counts.get(house.block_id) ?? 0) + 1,
                ),
            );

            return counts;
        },
        [session.db],
        new Map<number, number>(),
    );

    return (
        <div className="space-y-4">
            <Card>
                <CardHeader>
                    <CardTitle>Campaign</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2">
                    {campaigns.length === 0 && !pkg ? (
                        <p className="text-sm text-muted-foreground">
                            No active assignment. Connect to the internet to
                            check for new assignments.
                        </p>
                    ) : (
                        <NativeSelect
                            aria-label="Campaign"
                            value={ctx?.campaignId ?? ''}
                            onChange={(event) =>
                                void chooseCampaign(Number(event.target.value))
                            }
                        >
                            {!ctx && (
                                <option value="">Select a campaign…</option>
                            )}
                            {campaigns.map((campaign) => (
                                <option key={campaign.id} value={campaign.id}>
                                    {campaign.name} ({campaign.reference_code})
                                </option>
                            ))}
                            {pkg &&
                                !campaigns.some(
                                    (campaign) =>
                                        campaign.id === pkg.campaign.id,
                                ) && (
                                    <option value={pkg.campaign.id}>
                                        {pkg.campaign.name} (offline copy)
                                    </option>
                                )}
                        </NativeSelect>
                    )}
                    {pkg?.campaign.instructions && (
                        <details className="text-sm">
                            <summary className="cursor-pointer text-muted-foreground">
                                Enumeration instructions
                            </summary>
                            <p className="mt-2 whitespace-pre-line">
                                {pkg.campaign.instructions}
                            </p>
                        </details>
                    )}
                    {pkg && (
                        <p className="text-xs text-muted-foreground">
                            Ages are recorded as of{' '}
                            {pkg.campaign.reference_date}.
                        </p>
                    )}
                </CardContent>
            </Card>

            {ctx && (
                <Card>
                    <CardHeader>
                        <CardTitle>Your blocks</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {blocks.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No blocks in your assignment yet.
                            </p>
                        ) : (
                            <ul className="divide-y rounded-md border">
                                {blocks.map((block) => (
                                    <li key={block.id}>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                navigate({
                                                    name: 'block',
                                                    blockId: block.id,
                                                })
                                            }
                                            className="flex w-full items-center justify-between gap-2 px-3 py-3 text-left hover:bg-accent"
                                        >
                                            <span>
                                                <span className="block font-medium">
                                                    {block.name}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {[
                                                        block.community,
                                                        block.town,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </span>
                                            </span>
                                            <span className="flex items-center gap-2 text-sm text-muted-foreground">
                                                {houseCounts.get(block.id) ?? 0}{' '}
                                                houses
                                                <ChevronRight className="size-4" />
                                            </span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

export function FieldBlock({ blockId }: { blockId: number }) {
    const { session, navigate } = useField();
    const ctx = useFieldContext();
    const [adding, setAdding] = useState(false);

    const block = useLiveQuery(() => session.db.blocks.get(blockId), [blockId]);
    const houses = useLiveQuery(
        () => session.db.houses.where('block_id').equals(blockId).toArray(),
        [blockId],
        [],
    );
    const visits = useLiveQuery(() => session.db.visits.toArray(), [], []);
    const visitByHouse = new Map(
        visits.map((visit) => [visit.house_uuid, visit]),
    );

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between gap-2">
                <div>
                    <h2 className="text-lg font-semibold">{block?.name}</h2>
                    <p className="text-xs text-muted-foreground">
                        {block?.community}
                    </p>
                </div>
                <Button onClick={() => setAdding(true)}>
                    <Plus /> House
                </Button>
            </div>

            {adding && (
                <Card>
                    <CardHeader>
                        <CardTitle>New house</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <HouseForm
                            blockId={blockId}
                            ctx={ctx}
                            onDone={(house) => {
                                setAdding(false);

                                if (house) {
                                    navigate({
                                        name: 'house',
                                        houseUuid: house.uuid,
                                    });
                                }
                            }}
                        />
                    </CardContent>
                </Card>
            )}

            <ul className="divide-y rounded-md border">
                {houses.length === 0 && (
                    <li className="p-4 text-sm text-muted-foreground">
                        No houses recorded on this block yet.
                    </li>
                )}
                {houses
                    .slice()
                    .sort((a, b) =>
                        (a.local_house_number ?? '').localeCompare(
                            b.local_house_number ?? '',
                            undefined,
                            { numeric: true },
                        ),
                    )
                    .map((house) => {
                        const visit = visitByHouse.get(house.uuid);

                        return (
                            <li key={house.uuid}>
                                <button
                                    type="button"
                                    onClick={() =>
                                        navigate({
                                            name: 'house',
                                            houseUuid: house.uuid,
                                        })
                                    }
                                    className="flex w-full items-center justify-between gap-2 px-3 py-3 text-left hover:bg-accent"
                                >
                                    <span className="flex items-center gap-2">
                                        <Home className="size-4 text-muted-foreground" />
                                        <span>
                                            <span className="block font-medium">
                                                House{' '}
                                                {house.local_house_number ??
                                                    '(no number)'}
                                            </span>
                                            <span className="block font-mono text-xs text-muted-foreground">
                                                {house.reference_code ??
                                                    'not yet synced'}
                                            </span>
                                        </span>
                                    </span>
                                    <span className="flex flex-col items-end gap-1">
                                        {visit ? (
                                            <StatusBadge
                                                status={visit.occupancy_status}
                                            />
                                        ) : (
                                            <span className="text-xs text-muted-foreground">
                                                Not visited
                                            </span>
                                        )}
                                        <SyncBadge state={house.sync} />
                                    </span>
                                </button>
                            </li>
                        );
                    })}
            </ul>
        </div>
    );
}

export function FieldQueue() {
    const { session, syncNow, syncing, online } = useField();
    const operations = useLiveQuery(
        () =>
            session.db.outbox
                .orderBy('seq')
                .reverse()
                .filter((op) => op.state !== 'synced')
                .toArray(),
        [],
        [],
    );
    const history = useLiveQuery(
        () => session.db.history.orderBy('id').reverse().limit(10).toArray(),
        [],
        [],
    );

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold">Sync queue</h2>
                <Button
                    onClick={() => void syncNow()}
                    disabled={syncing || !online}
                >
                    {syncing ? 'Synchronising…' : 'Sync now'}
                </Button>
            </div>
            <ul className="divide-y rounded-md border">
                {operations.length === 0 && (
                    <li className="p-4 text-sm text-muted-foreground">
                        Everything on this device has been synchronised.
                    </li>
                )}
                {operations.map((op) => (
                    <li key={op.op_uuid} className="space-y-1 p-3 text-sm">
                        <div className="flex items-center justify-between gap-2">
                            <span className="font-medium capitalize">
                                {op.entity_type} · {op.operation}
                            </span>
                            <StatusBadge
                                status={
                                    op.state === 'retry' ? 'pending' : op.state
                                }
                                label={op.state}
                            />
                        </div>
                        <div className="text-xs text-muted-foreground">
                            Queued {new Date(op.created_at).toLocaleString()}
                            {op.attempts > 0 &&
                                ` · ${op.attempts} attempt${op.attempts === 1 ? '' : 's'}`}
                        </div>
                        {op.last_error && (
                            <p className="text-xs text-red-700 dark:text-red-400">
                                {op.last_error}
                            </p>
                        )}
                    </li>
                ))}
            </ul>
            <Card>
                <CardHeader>
                    <CardTitle>Recent synchronisations</CardTitle>
                </CardHeader>
                <CardContent>
                    <ul className="space-y-1 text-sm">
                        {history.length === 0 && (
                            <li className="text-muted-foreground">None yet.</li>
                        )}
                        {history.map((entry) => (
                            <li key={entry.id}>
                                {new Date(entry.at).toLocaleString()}:{' '}
                                {entry.applied} applied, {entry.rejected}{' '}
                                rejected, {entry.conflicts} conflicts,{' '}
                                {entry.retryable} to retry
                            </li>
                        ))}
                    </ul>
                </CardContent>
            </Card>
        </div>
    );
}

export function FieldSettings() {
    const { session, lock, signOut } = useField();
    const pending = useLiveQuery(() => unsyncedCount(session.db), [], 0);

    return (
        <div className="space-y-4">
            <Card>
                <CardHeader>
                    <CardTitle>This device</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2 text-sm">
                    <p>Signed in as {session.device.user_name}.</p>
                    <p className="text-muted-foreground">
                        Offline work allowed until{' '}
                        {session.device.offline_expires_at
                            ? new Date(
                                  session.device.offline_expires_at,
                              ).toLocaleString()
                            : 'the next connection'}
                        . Connecting to the internet extends this.
                    </p>
                    <p className="text-muted-foreground">
                        Keep the device screen lock on, never share your PIN,
                        and report a lost device to your supervisor immediately
                        so it can be revoked.
                    </p>
                </CardContent>
            </Card>
            <div className="grid gap-2 sm:grid-cols-2">
                <Button variant="outline" onClick={lock}>
                    <Lock /> Lock now
                </Button>
                <Button
                    variant="destructive"
                    onClick={async () => {
                        const warning =
                            pending > 0
                                ? `${pending} change(s) have not been synchronised and will be lost. Sign out and erase this device anyway?`
                                : 'Sign out and erase all field data from this device?';

                        if (window.confirm(warning)) {
                            await signOut();
                        }
                    }}
                >
                    <LogOut /> Sign out and erase
                </Button>
            </div>
            <p className="text-xs text-muted-foreground">
                Erasing removes the local database, keys and cached app files
                from this browser. It cannot remove copies held in
                operating-system backups or recovered by forensic tools, which
                is why data is stored encrypted.
            </p>
        </div>
    );
}
