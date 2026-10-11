/**
 * Minimal HTTP client for the versioned field API.
 */

export class OfflineError extends Error {
    constructor(message = 'No connection to the server.') {
        super(message);
        this.name = 'OfflineError';
    }
}

export class DeviceRejectedError extends Error {
    constructor(
        public readonly reason: string,
        public readonly wipe: boolean,
        message: string,
    ) {
        super(message);
        this.name = 'DeviceRejectedError';
    }
}

export class HttpError extends Error {
    constructor(
        public readonly status: number,
        public readonly body: unknown,
        message: string,
    ) {
        super(message);
        this.name = 'HttpError';
    }
}

export type FieldApi = {
    get<T>(path: string): Promise<T>;
    post<T>(path: string, body: unknown): Promise<T>;
    delete<T>(path: string): Promise<T>;
};

const TIMEOUT_MS = 30_000;

export function createFieldApi(
    token: string,
    deviceUuid: string,
    baseUrl = '',
): FieldApi {
    async function request<T>(
        method: string,
        path: string,
        body?: unknown,
    ): Promise<T> {
        if (typeof navigator !== 'undefined' && navigator.onLine === false) {
            throw new OfflineError();
        }

        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), TIMEOUT_MS);
        let response: Response;

        try {
            response = await fetch(`${baseUrl}${path}`, {
                method,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    Authorization: `Bearer ${token}`,
                    'X-Device-Uuid': deviceUuid,
                },
                body: body === undefined ? undefined : JSON.stringify(body),
                signal: controller.signal,
                credentials: 'omit',
            });
        } catch {
            throw new OfflineError();
        } finally {
            clearTimeout(timeout);
        }

        const payload: unknown = await response.json().catch(() => null);

        if (response.status === 401 || response.status === 403) {
            const data = (payload ?? {}) as {
                reason?: string;
                wipe?: boolean;
                message?: string;
            };
            let wipe = data.wipe === true;

            if (!wipe && response.status === 401) {
                wipe = await isDeviceRevoked(deviceUuid, baseUrl);
            }

            throw new DeviceRejectedError(
                data.reason ?? 'unauthenticated',
                wipe,
                data.message ?? 'The server no longer accepts this device.',
            );
        }

        if (!response.ok) {
            const message =
                (payload as { message?: string } | null)?.message ??
                `Server error ${response.status}`;

            throw new HttpError(response.status, payload, message);
        }

        return payload as T;
    }

    return {
        get: (path) => request('GET', path),
        post: (path, body) => request('POST', path, body),
        delete: (path) => request('DELETE', path),
    };
}

export async function isDeviceRevoked(
    deviceUuid: string,
    baseUrl = '',
): Promise<boolean> {
    try {
        const response = await fetch(
            `${baseUrl}/api/v1/devices/${deviceUuid}/status`,
            { headers: { Accept: 'application/json' }, credentials: 'omit' },
        );
        const body = (await response.json()) as { revoked?: boolean };

        return body.revoked === true;
    } catch {
        return false;
    }
}

/**
 * Reads Laravel's XSRF cookie for the one web-session request the field app
 * makes (device registration).
 */
export function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}
