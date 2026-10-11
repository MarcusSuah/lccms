import { createContext, useContext } from 'react';
import type { PackageInfo } from '@/offline/db';
import type { FieldContext } from '@/offline/repository';
import type { UnlockedSession } from '@/offline/session';
import type { SyncReport } from '@/offline/sync';

export type FieldView =
    | { name: 'home' }
    | { name: 'block'; blockId: number }
    | { name: 'house'; houseUuid: string }
    | { name: 'household'; householdUuid: string }
    | { name: 'queue' }
    | { name: 'settings' };

export type FieldState = {
    session: UnlockedSession;
    ctx: FieldContext | null;
    pkg: PackageInfo | null;
    online: boolean;
    syncing: boolean;
    lastReport: SyncReport | null;
    lastError: string | null;
    view: FieldView;
    navigate: (view: FieldView) => void;
    syncNow: () => Promise<void>;
    chooseCampaign: (campaignId: number) => Promise<void>;
    lock: () => void;
    signOut: () => Promise<void>;
};

export const FieldStateContext = createContext<FieldState | null>(null);

export function useField(): FieldState {
    const state = useContext(FieldStateContext);

    if (!state) {
        throw new Error('useField must be used inside the field application.');
    }

    return state;
}

export function useFieldContext(): FieldContext {
    const { ctx } = useField();

    if (!ctx) {
        throw new Error('Choose a campaign first.');
    }

    return ctx;
}

export function optionLabel(
    pkg: PackageInfo | null,
    group: string,
    value: string | null | undefined,
): string {
    if (!value) {
        return '—';
    }

    return (
        pkg?.options[group]?.find((option) => option.value === value)?.label ??
        value
    );
}
