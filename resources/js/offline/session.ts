import { createFieldApi, xsrfToken } from './api';
import type { FieldApi } from './api';
import {
    MAX_PIN_ATTEMPTS,
    createVault,
    isAcceptablePin,
    open,
    openVault,
    seal,
} from './crypto';
import type { VaultMeta } from './crypto';
import { fieldDb, wipeFieldDb } from './db';
import type { DeviceInfo, FieldDatabase } from './db';

/**
 * Device session lifecycle: registration (online, signed in), PIN unlock,
 * offline expiry and secure sign-out.
 */

export type UnlockedSession = {
    db: FieldDatabase;
    key: CryptoKey;
    token: string;
    device: DeviceInfo;
    api: FieldApi;
};

export type UnlockResult =
    | { ok: true; session: UnlockedSession }
    | { ok: false; attemptsLeft: number; wiped: boolean };

type RegistrationResponse = {
    device_uuid: string;
    token: string;
    user: { id: number; name: string };
    offline_expires_at: string | null;
};

export async function deviceInfo(
    db: FieldDatabase = fieldDb(),
): Promise<DeviceInfo | null> {
    const row = await db.meta.get('device');

    return row ? (row.value as DeviceInfo) : null;
}

/**
 * Registers this browser as a field device and protects the issued token and
 * all local data with the chosen PIN.
 */
export async function registerDevice(
    label: string,
    pin: string,
    registrationUrl: string,
): Promise<UnlockedSession> {
    if (!isAcceptablePin(pin)) {
        throw new Error(
            'Choose a PIN of at least 6 digits that is not a simple sequence.',
        );
    }

    const response = await fetch(registrationUrl, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify({ label }),
    });

    const body = (await response.json().catch(() => null)) as
        | (RegistrationResponse & { message?: string })
        | null;

    if (!response.ok || !body?.token) {
        throw new Error(body?.message ?? 'The device could not be registered.');
    }

    const db = fieldDb();
    const { meta, key } = await createVault(pin);
    const device: DeviceInfo = {
        device_uuid: body.device_uuid,
        user_id: body.user.id,
        user_name: body.user.name,
        offline_expires_at: body.offline_expires_at,
        registered_at: new Date().toISOString(),
    };
    const sealedToken = await seal(key, { token: body.token });

    await db.transaction('rw', db.meta, async () => {
        await db.meta.put({ key: 'vault', value: meta });
        await db.meta.put({ key: 'device', value: device });
        await db.meta.put({ key: 'session', value: sealedToken });
    });

    return {
        db,
        key,
        token: body.token,
        device,
        api: createFieldApi(body.token, device.device_uuid),
    };
}

/**
 * Opens the vault with the PIN. After MAX_PIN_ATTEMPTS consecutive failures
 * the local database is wiped: unsynced data is lost, but cannot be read.
 */
export async function unlock(
    pin: string,
    db: FieldDatabase = fieldDb(),
): Promise<UnlockResult> {
    const vaultRow = await db.meta.get('vault');
    const sessionRow = await db.meta.get('session');
    const device = await deviceInfo(db);

    if (!vaultRow || !sessionRow || !device) {
        return { ok: false, attemptsLeft: 0, wiped: false };
    }

    const vault = vaultRow.value as VaultMeta;
    const key = await openVault(pin, vault);

    if (!key) {
        const failedAttempts = vault.failedAttempts + 1;

        if (failedAttempts >= MAX_PIN_ATTEMPTS) {
            await wipeDevice(db);

            return { ok: false, attemptsLeft: 0, wiped: true };
        }

        await db.meta.put({
            key: 'vault',
            value: { ...vault, failedAttempts },
        });

        return {
            ok: false,
            attemptsLeft: MAX_PIN_ATTEMPTS - failedAttempts,
            wiped: false,
        };
    }

    if (vault.failedAttempts > 0) {
        await db.meta.put({
            key: 'vault',
            value: { ...vault, failedAttempts: 0 },
        });
    }

    const { token } = await open<{ token: string }>(
        key,
        sessionRow.value as { iv: string; ct: string },
    );

    return {
        ok: true,
        session: {
            db,
            key,
            token,
            device,
            api: createFieldApi(token, device.device_uuid),
        },
    };
}

/**
 * Whether the offline window has passed. The device must reach the server
 * (which extends the window) before field work continues.
 */
export function isOfflineWindowExpired(
    device: DeviceInfo,
    now = new Date(),
): boolean {
    return (
        device.offline_expires_at !== null &&
        new Date(device.offline_expires_at).getTime() < now.getTime()
    );
}

export async function recordServerContact(
    db: FieldDatabase,
    device: DeviceInfo,
    offlineExpiresAt: string | null,
): Promise<DeviceInfo> {
    const updated = { ...device, offline_expires_at: offlineExpiresAt };
    await db.meta.put({ key: 'device', value: updated });

    return updated;
}

/**
 * Deletes every trace the application keeps on this device: the IndexedDB
 * database (records, outbox, keys) and the service-worker caches.
 */
export async function wipeDevice(db: FieldDatabase = fieldDb()): Promise<void> {
    await wipeFieldDb(db);

    if (typeof caches !== 'undefined') {
        const keys = await caches.keys();
        await Promise.all(
            keys
                .filter((key) => key.startsWith('lccms-field-'))
                .map((key) => caches.delete(key)),
        );
    }

    navigator.serviceWorker?.controller?.postMessage('wipe');
}

/**
 * Secure sign-out: revoke the device token on the server when reachable,
 * then wipe local data. Callers must warn about unsynced operations first.
 */
export async function signOutDevice(session: UnlockedSession): Promise<void> {
    try {
        await session.api.delete('/api/v1/field/device');
    } catch {
        // Offline: the local wipe still happens; an administrator can revoke the device.
    }

    await wipeDevice();
}

export async function unsyncedCount(db: FieldDatabase): Promise<number> {
    return db.outbox.filter((op) => op.state !== 'synced').count();
}
