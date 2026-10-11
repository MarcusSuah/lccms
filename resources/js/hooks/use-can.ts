import { usePage } from '@inertiajs/react';

/**
 * Permission checks for showing or hiding UI. This is a usability aid only:
 * every action is authorized again on the server.
 */
export function useCan(): (...permissions: string[]) => boolean {
    const { auth } = usePage().props;
    const granted = new Set(auth.permissions ?? []);

    return (...permissions: string[]) =>
        permissions.some((permission) => granted.has(permission));
}
