/**
 * At-rest encryption for the field device.
 *
 * A random AES-256-GCM data key encrypts every sensitive value stored in
 * IndexedDB. The data key is itself wrapped (AES-KW) with a key derived from
 * the enumerator's offline PIN using PBKDF2-SHA-256. Only the wrapped key and
 * a random salt are persisted; the PIN and the unwrapped key never are. While
 * unlocked, the data key exists only in memory as a non-extractable CryptoKey.
 *
 * Nothing secret ships in the JavaScript bundle.
 */

export const PBKDF2_ITERATIONS = 600_000;
export const MAX_PIN_ATTEMPTS = 5;
export const MIN_PIN_LENGTH = 6;

export type VaultMeta = {
    salt: string;
    wrappedKey: string;
    iterations: number;
    failedAttempts: number;
    createdAt: string;
};

export type Sealed = {
    iv: string;
    ct: string;
};

const encoder = new TextEncoder();
const decoder = new TextDecoder();

export function toBase64(bytes: ArrayBuffer | Uint8Array): string {
    const view = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);
    let binary = '';

    for (const byte of view) {
        binary += String.fromCharCode(byte);
    }

    return btoa(binary);
}

export function fromBase64(value: string): Uint8Array<ArrayBuffer> {
    const binary = atob(value);
    const bytes = new Uint8Array(new ArrayBuffer(binary.length));

    for (let index = 0; index < binary.length; index++) {
        bytes[index] = binary.charCodeAt(index);
    }

    return bytes;
}

export function isAcceptablePin(pin: string): boolean {
    if (!/^\d+$/.test(pin) || pin.length < MIN_PIN_LENGTH) {
        return false;
    }

    // Reject trivially guessable PINs such as 000000 or 123456.
    const allSame = /^(\d)\1+$/.test(pin);
    const ascending = '01234567890123456789'.includes(pin);
    const descending = '98765432109876543210'.includes(pin);

    return !allSame && !ascending && !descending;
}

async function deriveWrappingKey(
    pin: string,
    salt: Uint8Array<ArrayBuffer>,
    iterations: number,
): Promise<CryptoKey> {
    const material = await crypto.subtle.importKey(
        'raw',
        encoder.encode(pin),
        'PBKDF2',
        false,
        ['deriveKey'],
    );

    return crypto.subtle.deriveKey(
        { name: 'PBKDF2', salt, iterations, hash: 'SHA-256' },
        material,
        { name: 'AES-KW', length: 256 },
        false,
        ['wrapKey', 'unwrapKey'],
    );
}

/**
 * Creates a new vault for a PIN. Returns the persisted metadata and the
 * in-memory (non-extractable) data key.
 */
export async function createVault(
    pin: string,
    iterations = PBKDF2_ITERATIONS,
): Promise<{ meta: VaultMeta; key: CryptoKey }> {
    const salt = crypto.getRandomValues(new Uint8Array(new ArrayBuffer(16)));
    const extractable = await crypto.subtle.generateKey(
        { name: 'AES-GCM', length: 256 },
        true,
        ['encrypt', 'decrypt'],
    );
    const wrappingKey = await deriveWrappingKey(pin, salt, iterations);
    const wrapped = await crypto.subtle.wrapKey(
        'raw',
        extractable,
        wrappingKey,
        'AES-KW',
    );

    const meta: VaultMeta = {
        salt: toBase64(salt),
        wrappedKey: toBase64(wrapped),
        iterations,
        failedAttempts: 0,
        createdAt: new Date().toISOString(),
    };

    return { meta, key: await unwrap(pin, meta) };
}

async function unwrap(pin: string, meta: VaultMeta): Promise<CryptoKey> {
    const wrappingKey = await deriveWrappingKey(
        pin,
        fromBase64(meta.salt),
        meta.iterations,
    );

    return crypto.subtle.unwrapKey(
        'raw',
        fromBase64(meta.wrappedKey),
        wrappingKey,
        'AES-KW',
        { name: 'AES-GCM', length: 256 },
        false,
        ['encrypt', 'decrypt'],
    );
}

/**
 * Unlocks the vault. A wrong PIN fails AES-KW integrity and returns null.
 */
export async function openVault(
    pin: string,
    meta: VaultMeta,
): Promise<CryptoKey | null> {
    try {
        return await unwrap(pin, meta);
    } catch {
        return null;
    }
}

export async function seal(key: CryptoKey, value: unknown): Promise<Sealed> {
    const iv = crypto.getRandomValues(new Uint8Array(new ArrayBuffer(12)));
    const ciphertext = await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv },
        key,
        encoder.encode(JSON.stringify(value)),
    );

    return { iv: toBase64(iv), ct: toBase64(ciphertext) };
}

export async function open<T>(key: CryptoKey, sealed: Sealed): Promise<T> {
    const plaintext = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv: fromBase64(sealed.iv) },
        key,
        fromBase64(sealed.ct),
    );

    return JSON.parse(decoder.decode(plaintext)) as T;
}
