import { Head, Link } from '@inertiajs/react';
import { useLiveQuery } from 'dexie-react-hooks';
import {
    CloudOff,
    CloudUpload,
    Home,
    ListChecks,
    RefreshCw,
    Settings,
    Wifi,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { FieldStateContext } from '@/components/field/field-context';
import type { FieldView } from '@/components/field/field-context';
import { FieldSetup, FieldUnlock } from '@/components/field/field-gate';
import {
    FieldBlock,
    FieldHome,
    FieldQueue,
    FieldSettings,
} from '@/components/field/field-home';
import { FieldHouse } from '@/components/field/field-house';
import { FieldHousehold } from '@/components/field/field-household';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { DeviceRejectedError, OfflineError } from '@/offline/api';
import type { DeviceInfo, PackageInfo } from '@/offline/db';
import type { FieldContext } from '@/offline/repository';
import {
    deviceInfo,
    isOfflineWindowExpired,
    recordServerContact,
    signOutDevice,
    wipeDevice,
} from '@/offline/session';
import type { UnlockedSession } from '@/offline/session';
import { refreshPackage, syncOutbox } from '@/offline/sync';
import type { SyncReport } from '@/offline/sync';
import { dashboard } from '@/routes';

const IDLE_LOCK_MS = 15 * 60_000;
const AUTO_SYNC_MS = 2 * 60_000;

type Campaign = { id: number; name: string; reference_code: string };

type ServerSession = {
    device: { offline_expires_at: string | null };
    campaigns: Campaign[];
    limits: { max_batch_operations: number };
};

function useOnline(): boolean {
    const [online, setOnline] = useState(() =>
        typeof navigator === 'undefined' ? true : navigator.onLine,
    );

    useEffect(() => {
        const update = () => setOnline(navigator.onLine);
        window.addEventListener('online', update);
        window.addEventListener('offline', update);

        return () => {
            window.removeEventListener('online', update);
            window.removeEventListener('offline', update);
        };
    }, []);

    return online;
}

function FieldWorkspace({
    session,
    online,
    onLock,
    onWiped,
}: {
    session: UnlockedSession;
    online: boolean;
    onLock: (message?: string) => void;
    onWiped: () => void;
}) {
    const [view, setView] = useState<FieldView>({ name: 'home' });
    const [pkg, setPkg] = useState<PackageInfo | null>(null);
    const [ctx, setCtx] = useState<FieldContext | null>(null);
    const [campaigns, setCampaigns] = useState<Campaign[]>([]);
    const [device, setDevice] = useState<DeviceInfo>(session.device);
    const [syncing, setSyncing] = useState(false);
    const [lastReport, setLastReport] = useState<SyncReport | null>(null);
    const [lastError, setLastError] = useState<string | null>(null);
    const [maxBatch, setMaxBatch] = useState(100);
    const syncingRef = useRef(false);

    const pending = useLiveQuery(
        () =>
            session.db.outbox
                .filter((op) => op.state === 'pending' || op.state === 'retry')
                .count(),
        [],
        0,
    );
    const attention = useLiveQuery(
        () =>
            session.db.outbox
                .filter(
                    (op) => op.state === 'failed' || op.state === 'conflict',
                )
                .count(),
        [],
        0,
    );

    const handleError = useCallback(
        async (exception: unknown) => {
            if (exception instanceof DeviceRejectedError) {
                if (exception.wipe) {
                    await wipeDevice();
                    onWiped();

                    return;
                }

                onLock(exception.message);

                return;
            }

            if (exception instanceof OfflineError) {
                setLastError(null);

                return;
            }

            setLastError(
                exception instanceof Error
                    ? exception.message
                    : 'Synchronisation failed.',
            );
        },
        [onLock, onWiped],
    );

    // Restore the last campaign package kept on the device.
    useEffect(() => {
        void session.db.meta.get('package').then((row) => {
            if (row) {
                const stored = row.value as PackageInfo;
                setPkg(stored);
                setCtx({
                    db: session.db,
                    key: session.key,
                    campaignId: stored.campaign_id,
                });
            }
        });
    }, [session]);

    // Confirm the device with the server whenever we are online.
    useEffect(() => {
        if (!online) {
            return;
        }

        void session.api
            .get<ServerSession>('/api/v1/field/session')
            .then(async (server) => {
                setCampaigns(server.campaigns);
                setMaxBatch(server.limits.max_batch_operations);
                setDevice(
                    await recordServerContact(
                        session.db,
                        device,
                        server.device.offline_expires_at,
                    ),
                );
            })
            .catch(handleError);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [online, session]);

    const syncNow = useCallback(async () => {
        if (syncingRef.current || !ctx) {
            return;
        }

        syncingRef.current = true;
        setSyncing(true);
        setLastError(null);

        try {
            const report = await syncOutbox(
                session.db,
                session.key,
                session.api,
                maxBatch,
            );
            setLastReport(report);
            setPkg(
                await refreshPackage(
                    session.db,
                    session.key,
                    session.api,
                    ctx.campaignId,
                ),
            );
        } catch (exception) {
            await handleError(exception);
        } finally {
            syncingRef.current = false;
            setSyncing(false);
        }
    }, [ctx, session, maxBatch, handleError]);

    // Synchronise automatically when connectivity returns and periodically.
    useEffect(() => {
        if (!online || !ctx) {
            return;
        }

        void syncNow();
        const interval = window.setInterval(() => void syncNow(), AUTO_SYNC_MS);

        return () => window.clearInterval(interval);
    }, [online, ctx, syncNow]);

    // Lock after inactivity so the key leaves memory.
    useEffect(() => {
        let timer = window.setTimeout(
            () => onLock('Locked after inactivity.'),
            IDLE_LOCK_MS,
        );
        const reset = () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(
                () => onLock('Locked after inactivity.'),
                IDLE_LOCK_MS,
            );
        };
        const events = ['pointerdown', 'keydown', 'touchstart'];
        events.forEach((name) => window.addEventListener(name, reset));

        return () => {
            window.clearTimeout(timer);
            events.forEach((name) => window.removeEventListener(name, reset));
        };
    }, [onLock]);

    const chooseCampaign = useCallback(
        async (campaignId: number) => {
            const nextCtx = { db: session.db, key: session.key, campaignId };
            setCtx(nextCtx);
            setView({ name: 'home' });

            if (online) {
                try {
                    setPkg(
                        await refreshPackage(
                            session.db,
                            session.key,
                            session.api,
                            campaignId,
                        ),
                    );
                } catch (exception) {
                    await handleError(exception);
                }
            }
        },
        [online, session, handleError],
    );

    const expired = isOfflineWindowExpired(device);

    const navItems: { view: FieldView; label: string; icon: typeof Home }[] = [
        { view: { name: 'home' }, label: 'Areas', icon: Home },
        { view: { name: 'queue' }, label: 'Sync', icon: ListChecks },
        { view: { name: 'settings' }, label: 'Device', icon: Settings },
    ];

    return (
        <FieldStateContext.Provider
            value={{
                session: { ...session, device },
                ctx,
                pkg,
                online,
                syncing,
                lastReport,
                lastError,
                view,
                navigate: setView,
                syncNow,
                chooseCampaign,
                lock: () => onLock(),
                signOut: async () => {
                    await signOutDevice(session);
                    onWiped();
                },
            }}
        >
            <div className="flex min-h-svh flex-col bg-background">
                <header className="sticky top-0 z-10 border-b bg-background/95 backdrop-blur">
                    <div className="mx-auto flex max-w-3xl items-center justify-between gap-2 px-4 py-2">
                        <div className="flex items-center gap-2">
                            <AppLogoIcon className="size-6 fill-current" />
                            <div className="leading-tight">
                                <div className="text-sm font-semibold">
                                    Field enumeration
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {pkg?.campaign.name ??
                                        'No campaign selected'}
                                </div>
                            </div>
                        </div>
                        <div className="flex items-center gap-2 text-xs">
                            <span
                                className={cn(
                                    'flex items-center gap-1',
                                    online
                                        ? 'text-emerald-700 dark:text-emerald-400'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {online ? (
                                    <Wifi className="size-4" />
                                ) : (
                                    <CloudOff className="size-4" />
                                )}
                                {online ? 'Online' : 'Offline'}
                            </span>
                            {pending > 0 && (
                                <span className="flex items-center gap-1 text-sky-700 dark:text-sky-400">
                                    <CloudUpload className="size-4" /> {pending}
                                </span>
                            )}
                            <Button
                                size="icon"
                                variant="ghost"
                                aria-label="Sync now"
                                disabled={!online || syncing}
                                onClick={() => void syncNow()}
                            >
                                <RefreshCw
                                    className={cn(syncing && 'animate-spin')}
                                />
                            </Button>
                        </div>
                    </div>
                    {(attention > 0 || lastError) && (
                        <div className="mx-auto max-w-3xl px-4 pb-2 text-xs text-red-700 dark:text-red-400">
                            {lastError ??
                                `${attention} change(s) need attention. Open Sync to see why.`}
                        </div>
                    )}
                </header>

                <main className="mx-auto w-full max-w-3xl flex-1 p-4 pb-24">
                    {expired && !online ? (
                        <div className="space-y-2 rounded-lg border p-6 text-center">
                            <CloudOff className="mx-auto size-8 text-muted-foreground" />
                            <p className="font-medium">Connect to continue</p>
                            <p className="text-sm text-muted-foreground">
                                This device has worked offline for longer than
                                allowed. Your queued work is safe and encrypted;
                                connect to the internet to confirm the device
                                and continue.
                            </p>
                        </div>
                    ) : (
                        <>
                            {view.name === 'home' && (
                                <FieldHome campaigns={campaigns} />
                            )}
                            {view.name === 'block' && ctx && (
                                <FieldBlock blockId={view.blockId} />
                            )}
                            {view.name === 'house' && ctx && (
                                <FieldHouse houseUuid={view.houseUuid} />
                            )}
                            {view.name === 'household' && ctx && (
                                <FieldHousehold
                                    householdUuid={view.householdUuid}
                                />
                            )}
                            {view.name === 'queue' && <FieldQueue />}
                            {view.name === 'settings' && <FieldSettings />}
                        </>
                    )}
                </main>

                <nav
                    aria-label="Field navigation"
                    className="fixed inset-x-0 bottom-0 border-t bg-background"
                >
                    <ul className="mx-auto grid max-w-3xl grid-cols-3">
                        {navItems.map((item) => (
                            <li key={item.label}>
                                <button
                                    type="button"
                                    onClick={() => setView(item.view)}
                                    aria-current={
                                        view.name === item.view.name
                                            ? 'page'
                                            : undefined
                                    }
                                    className={cn(
                                        'flex w-full flex-col items-center gap-0.5 py-2 text-xs text-muted-foreground',
                                        view.name === item.view.name &&
                                            'text-foreground',
                                    )}
                                >
                                    <item.icon className="size-5" />
                                    {item.label}
                                </button>
                            </li>
                        ))}
                    </ul>
                </nav>
            </div>
        </FieldStateContext.Provider>
    );
}

export default function FieldApp() {
    const online = useOnline();
    const [phase, setPhase] = useState<
        'loading' | 'setup' | 'locked' | 'ready'
    >('loading');
    const [device, setDevice] = useState<DeviceInfo | null>(null);
    const [session, setSession] = useState<UnlockedSession | null>(null);
    const [lockMessage, setLockMessage] = useState<string | null>(null);

    useEffect(() => {
        if ('serviceWorker' in navigator) {
            void navigator.serviceWorker
                .register('/field-sw.js', { scope: '/' })
                .catch(() => undefined);
        }

        void deviceInfo().then((info) => {
            setDevice(info);
            setPhase(info ? 'locked' : 'setup');
        });
    }, []);

    const lock = useCallback((message?: string) => {
        setSession(null);
        setLockMessage(message ?? null);
        setPhase('locked');
    }, []);

    const wiped = useCallback(() => {
        setSession(null);
        setDevice(null);
        setPhase('setup');
    }, []);

    if (phase === 'ready' && session) {
        return (
            <>
                <Head title="Field enumeration" />
                <FieldWorkspace
                    session={session}
                    online={online}
                    onLock={lock}
                    onWiped={wiped}
                />
            </>
        );
    }

    return (
        <>
            <Head title="Field enumeration" />
            <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-muted/40 p-4">
                <div className="flex items-center gap-2">
                    <AppLogoIcon className="size-8 fill-current" />
                    <span className="text-lg font-semibold">
                        Field enumeration
                    </span>
                </div>
                {phase === 'setup' && (
                    <FieldSetup
                        online={online}
                        onReady={(created) => {
                            setDevice(created.device);
                            setSession(created);
                            setPhase('ready');
                        }}
                    />
                )}
                {phase === 'locked' && device && (
                    <FieldUnlock
                        userName={device.user_name}
                        message={lockMessage}
                        onUnlocked={(opened) => {
                            setSession(opened);
                            setLockMessage(null);
                            setPhase('ready');
                        }}
                        onWiped={wiped}
                    />
                )}
                <Link
                    href={dashboard()}
                    className="text-sm text-muted-foreground hover:underline"
                >
                    Back to the staff dashboard
                </Link>
            </div>
        </>
    );
}
