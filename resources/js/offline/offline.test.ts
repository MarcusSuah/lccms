import 'fake-indexeddb/auto';
import { afterEach, describe, expect, it } from 'vitest';
import { OfflineError } from './api';
import type { FieldApi } from './api';
import { createVault, isAcceptablePin, open, openVault, seal } from './crypto';
import { FieldDatabase } from './db';
import type { LocalHouse } from './db';
import {
    FieldValidationError,
    createHouse,
    createHousehold,
    createOccupant,
    submitHousehold,
    updateOccupant,
} from './repository';
import type { FieldContext } from './repository';
import { unlock } from './session';
import { nextBatch, syncOutbox } from './sync';
import type { SyncResult } from './sync';

const TEST_ITERATIONS = 1_000;
const databases: FieldDatabase[] = [];

async function context(name = crypto.randomUUID()): Promise<FieldContext> {
    const db = new FieldDatabase(name);
    databases.push(db);
    const { key } = await createVault('482915', TEST_ITERATIONS);

    return { db, key, campaignId: 7 };
}

const person = (name: string, relationship: string, age: number) => ({
    full_name: name,
    date_of_birth: null,
    age_years: age,
    age_is_estimated: false,
    sex: 'female',
    relationship_to_head: relationship,
    usual_resident: true,
    school_attendance: null,
});

const houseFields = {
    local_house_number: '12',
    building_type: 'compound',
    tenure: null,
    floors: 1,
    latitude: 6.3,
    longitude: -10.8,
    gps_accuracy_m: 8,
    notes: null,
};

/**
 * A server double that applies every operation and remembers what it saw.
 */
function fakeServer(): FieldApi & { received: Record<string, unknown>[] } {
    const received: Record<string, unknown>[] = [];
    let nextId = 100;

    return {
        received,
        async get<T>(): Promise<T> {
            return {} as T;
        },
        async delete<T>(): Promise<T> {
            return {} as T;
        },
        async post<T>(_path: string, body: unknown): Promise<T> {
            const operations = (
                body as { operations: Record<string, unknown>[] }
            ).operations;
            received.push(...operations);

            const results: SyncResult[] = operations.map((op) => ({
                op_uuid: op.op_uuid as string,
                status: 'applied',
                record: {
                    id: nextId++,
                    reference_code: `REF-${nextId}`,
                    version:
                        op.operation === 'create'
                            ? 1
                            : ((op.base_version as number) ?? 1) + 1,
                    status: op.operation === 'submit' ? 'submitted' : 'draft',
                },
            }));

            return { results } as T;
        },
    };
}

afterEach(async () => {
    while (databases.length > 0) {
        const db = databases.pop()!;
        db.close();
        await db.delete();
    }
});

describe('device encryption', () => {
    it('round-trips sealed values and rejects a wrong PIN', async () => {
        const { meta, key } = await createVault('482915', TEST_ITERATIONS);
        const sealed = await seal(key, { name: 'Musu' });

        expect(await open(key, sealed)).toEqual({ name: 'Musu' });
        expect(await openVault('000001', meta)).toBeNull();
        expect(await openVault('482915', meta)).not.toBeNull();
    });

    it('refuses weak PINs', () => {
        expect(isAcceptablePin('123456')).toBe(false);
        expect(isAcceptablePin('111111')).toBe(false);
        expect(isAcceptablePin('48291')).toBe(false);
        expect(isAcceptablePin('482915')).toBe(true);
    });

    it('wipes the device after five wrong PINs', async () => {
        const name = crypto.randomUUID();
        const db = new FieldDatabase(name);
        databases.push(db);
        const { meta, key } = await createVault('482915', TEST_ITERATIONS);
        await db.meta.put({ key: 'vault', value: meta });
        await db.meta.put({
            key: 'session',
            value: await seal(key, { token: 't' }),
        });
        await db.meta.put({
            key: 'device',
            value: {
                device_uuid: 'd',
                user_id: 1,
                user_name: 'E',
                offline_expires_at: null,
                registered_at: '',
            },
        });

        for (let attempt = 1; attempt <= 4; attempt++) {
            const result = await unlock('999999', db);
            expect(result.ok).toBe(false);
        }

        const correct = await unlock('482915', db);
        expect(correct.ok).toBe(true);

        for (let attempt = 1; attempt <= 4; attempt++) {
            await unlock('999999', db);
        }

        const final = await unlock('999999', db);
        const reopened = new FieldDatabase(name);
        databases.push(reopened);

        expect(final).toEqual({ ok: false, attemptsLeft: 0, wiped: true });
        expect(await reopened.meta.count()).toBe(0);
    });
});

describe('offline data entry', () => {
    it('records a house with several households and occupants, encrypting personal data', async () => {
        const ctx = await context();
        const house = await createHouse(ctx, 11, houseFields);
        const first = await createHousehold(ctx, house, {
            contact_name: 'Musu Kollie',
            contact_phone: null,
            residence_type: 'usual',
        });
        const second = await createHousehold(ctx, house, {
            contact_name: null,
            contact_phone: null,
            residence_type: 'usual',
        });

        await createOccupant(ctx, first, person('Musu Kollie', 'head', 41));
        await createOccupant(ctx, first, person('Junior Kollie', 'child', 7));
        await createOccupant(ctx, second, person('Hawa Doe', 'head', 33));

        const ops = await ctx.db.outbox.orderBy('seq').toArray();
        const rawOccupants = JSON.stringify(await ctx.db.occupants.toArray());
        const rawHouses = JSON.stringify(await ctx.db.houses.toArray());

        expect(ops.map((op) => `${op.entity_type}.${op.operation}`)).toEqual([
            'house.create',
            'household.create',
            'household.create',
            'occupant.create',
            'occupant.create',
            'occupant.create',
        ]);
        expect(
            await ctx.db.households
                .where('house_uuid')
                .equals(house.uuid)
                .count(),
        ).toBe(2);
        expect(rawOccupants).not.toContain('Musu Kollie');
        expect(rawHouses).not.toContain('-10.8');
    });

    it('keeps queued drafts when the browser is closed and reopened', async () => {
        const name = crypto.randomUUID();
        const ctx = await context(name);
        const house = await createHouse(ctx, 11, houseFields);
        await createHousehold(ctx, house, {
            contact_name: null,
            contact_phone: null,
            residence_type: 'usual',
        });
        ctx.db.close();

        const reopened = new FieldDatabase(name);
        databases.push(reopened);
        await reopened.open();

        expect(await reopened.houses.count()).toBe(1);
        expect(await reopened.outbox.count()).toBe(2);
    });

    it('prevents a second head in one household', async () => {
        const ctx = await context();
        const house = await createHouse(ctx, 11, houseFields);
        const household = await createHousehold(ctx, house, {
            contact_name: null,
            contact_phone: null,
            residence_type: 'usual',
        });
        await createOccupant(ctx, household, person('Head One', 'head', 50));

        await expect(
            createOccupant(ctx, household, person('Head Two', 'head', 48)),
        ).rejects.toBeInstanceOf(FieldValidationError);
    });

    it('requires exactly one head before submitting', async () => {
        const ctx = await context();
        const house = await createHouse(ctx, 11, houseFields);
        const household = await createHousehold(ctx, house, {
            contact_name: null,
            contact_phone: null,
            residence_type: 'usual',
        });
        await createOccupant(ctx, household, person('Only Child', 'child', 6));

        await expect(submitHousehold(ctx, household)).rejects.toThrow(
            'Exactly one person',
        );
    });

    it('merges edits made before the record was ever sent', async () => {
        const ctx = await context();
        const house = await createHouse(ctx, 11, houseFields);
        const household = await createHousehold(ctx, house, {
            contact_name: null,
            contact_phone: null,
            residence_type: 'usual',
        });
        const occupant = await createOccupant(
            ctx,
            household,
            person('Tipo', 'head', 30),
        );
        await updateOccupant(
            ctx,
            household,
            occupant,
            person('Typo Fixed', 'head', 31),
        );

        const ops = await ctx.db.outbox
            .where('entity_uuid')
            .equals(occupant.uuid)
            .toArray();

        expect(ops).toHaveLength(1);
        expect(
            await open<{ full_name: string }>(ctx.key, ops[0].payload),
        ).toMatchObject({ full_name: 'Typo Fixed' });
    });
});

describe('synchronisation', () => {
    it('sends at most one operation per record per batch, oldest first', async () => {
        const ctx = await context();
        const house = await createHouse(ctx, 11, houseFields);
        const household = await createHousehold(ctx, house, {
            contact_name: null,
            contact_phone: null,
            residence_type: 'usual',
        });
        await createOccupant(ctx, household, person('Head', 'head', 40));
        await submitHousehold(ctx, household);

        const batch = await nextBatch(ctx.db, 100);

        expect(batch.map((op) => `${op.entity_type}.${op.operation}`)).toEqual([
            'house.create',
            'household.create',
            'occupant.create',
        ]);
    });

    it('synchronises everything and records server identifiers', async () => {
        const ctx = await context();
        const house = await createHouse(ctx, 11, houseFields);
        const household = await createHousehold(ctx, house, {
            contact_name: null,
            contact_phone: null,
            residence_type: 'usual',
        });
        await createOccupant(ctx, household, person('Head', 'head', 40));
        await submitHousehold(ctx, household);
        const server = fakeServer();

        const report = await syncOutbox(ctx.db, ctx.key, server, 100);
        const stored = await ctx.db.houses.get(house.uuid);

        expect(report.applied).toBe(4);
        expect(report.remaining).toBe(0);
        expect((stored as LocalHouse).id).not.toBeNull();
        expect((stored as LocalHouse).sync).toBe('synced');
        expect(
            server.received.find((op) => op.operation === 'submit')
                ?.base_version,
        ).toBe(1);
    });

    it('resends the same operation ids after a dropped connection', async () => {
        const ctx = await context();
        await createHouse(ctx, 11, houseFields);
        const server = fakeServer();
        let calls = 0;
        const flaky: FieldApi = {
            ...server,
            async post<T>(path: string, body: unknown): Promise<T> {
                calls++;
                await server.post(path, body);

                if (calls === 1) {
                    throw new OfflineError();
                }

                return server.post<T>(path, body);
            },
        };

        await expect(
            syncOutbox(ctx.db, ctx.key, flaky, 100),
        ).rejects.toBeInstanceOf(OfflineError);
        expect((await ctx.db.outbox.toArray())[0].state).toBe('pending');

        await syncOutbox(ctx.db, ctx.key, flaky, 100);

        const sentIds = server.received.map((op) => op.op_uuid);
        expect(new Set(sentIds).size).toBe(1);
        expect((await ctx.db.outbox.toArray())[0].state).toBe('synced');
    });

    it('marks rejected and conflicting operations for the enumerator', async () => {
        const ctx = await context();
        const house = await createHouse(ctx, 11, houseFields);
        const api: FieldApi = {
            ...fakeServer(),
            async post<T>(_path: string, body: unknown): Promise<T> {
                const op = (body as { operations: { op_uuid: string }[] })
                    .operations[0];

                return {
                    results: [
                        {
                            op_uuid: op.op_uuid,
                            status: 'rejected',
                            code: 'unauthorized',
                            errors: {
                                operation: [
                                    'You are not assigned to this area for this campaign.',
                                ],
                            },
                        },
                    ],
                } as T;
            },
        };

        const report = await syncOutbox(ctx.db, ctx.key, api, 100);
        const stored = (await ctx.db.houses.get(house.uuid)) as LocalHouse;

        expect(report.rejected).toBe(1);
        expect(stored.sync).toBe('failed');
        expect(stored.sync_message).toContain('not assigned');
    });
});
