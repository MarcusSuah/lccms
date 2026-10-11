import Dexie from 'dexie';
import { open, seal } from './crypto';
import type {
    EntityType,
    FieldDatabase,
    HouseFields,
    HouseholdFields,
    LocalHouse,
    LocalHousehold,
    LocalOccupant,
    LocalVisit,
    OccupantFields,
    OutboxOperation,
    VisitFields,
} from './db';

/**
 * Local data entry. Every change is written to the working copy and to the
 * outbox in one IndexedDB transaction, so a closed browser or a lost
 * connection never loses a confirmed entry.
 */

export type FieldContext = {
    db: FieldDatabase;
    key: CryptoKey;
    campaignId: number;
};

export class FieldValidationError extends Error {
    constructor(message: string) {
        super(message);
        this.name = 'FieldValidationError';
    }
}

const now = () => new Date().toISOString();

export function newUuid(): string {
    return crypto.randomUUID();
}

/**
 * Field edits are allowed while a household is a draft or was returned.
 */
export function isEditable(status: string): boolean {
    return status === 'draft' || status === 'rejected';
}

/**
 * Appends an operation to the outbox. An edit made before the previous change
 * to the same record was ever sent is merged into that unsent operation, so
 * the server sees one consistent change instead of a chain of stale versions.
 */
async function enqueue(
    ctx: FieldContext,
    entityType: EntityType,
    entityUuid: string,
    operation: string,
    payload: Record<string, unknown>,
): Promise<void> {
    const mergeable = ['create', 'update', 'upsert'];

    if (operation === 'update' || operation === 'upsert') {
        const unsent = await ctx.db.outbox
            .where('entity_uuid')
            .equals(entityUuid)
            .filter(
                (op) =>
                    op.state === 'pending' &&
                    op.sent_at === null &&
                    mergeable.includes(op.operation),
            )
            .last();

        if (unsent) {
            // Crypto runs outside IndexedDB; waitFor keeps the transaction open.
            const previous = await Dexie.waitFor(
                open<Record<string, unknown>>(ctx.key, unsent.payload),
            );
            const merged = await Dexie.waitFor(
                seal(ctx.key, { ...previous, ...payload }),
            );
            await ctx.db.outbox.update(unsent.seq!, { payload: merged });

            return;
        }
    }

    const op: OutboxOperation = {
        op_uuid: newUuid(),
        campaign_id: ctx.campaignId,
        entity_type: entityType,
        entity_uuid: entityUuid,
        operation,
        base_version: null,
        payload: await Dexie.waitFor(seal(ctx.key, payload)),
        state: 'pending',
        attempts: 0,
        sent_at: null,
        next_attempt_at: null,
        last_error: null,
        created_at: now(),
    };

    await ctx.db.outbox.add(op);
}

export async function createHouse(
    ctx: FieldContext,
    blockId: number,
    fields: HouseFields,
): Promise<LocalHouse> {
    const house: LocalHouse = {
        id: null,
        uuid: newUuid(),
        reference_code: null,
        version: 1,
        status: 'active',
        sync: 'pending',
        block_id: blockId,
        local_house_number: fields.local_house_number,
        building_type: fields.building_type,
        secure: await seal(ctx.key, fields),
        updated_at: now(),
    };

    await ctx.db.transaction('rw', ctx.db.houses, ctx.db.outbox, async () => {
        await ctx.db.houses.add(house);
        await enqueue(ctx, 'house', house.uuid, 'create', {
            ...fields,
            block_id: blockId,
        });
    });

    return house;
}

export async function updateHouse(
    ctx: FieldContext,
    house: LocalHouse,
    fields: HouseFields,
): Promise<void> {
    const secure = await seal(ctx.key, fields);

    await ctx.db.transaction('rw', ctx.db.houses, ctx.db.outbox, async () => {
        await ctx.db.houses.update(house.uuid, {
            local_house_number: fields.local_house_number,
            building_type: fields.building_type,
            secure,
            sync: 'pending',
            updated_at: now(),
        });
        await enqueue(ctx, 'house', house.uuid, 'update', { ...fields });
    });
}

export async function readHouse(
    ctx: FieldContext,
    house: LocalHouse,
): Promise<HouseFields> {
    if (house.secure) {
        return open<HouseFields>(ctx.key, house.secure);
    }

    return {
        local_house_number: house.local_house_number,
        building_type: house.building_type,
        tenure: null,
        floors: null,
        latitude: null,
        longitude: null,
        gps_accuracy_m: null,
        notes: null,
    };
}

export async function saveVisit(
    ctx: FieldContext,
    house: LocalHouse,
    fields: VisitFields,
): Promise<LocalVisit> {
    const existing = await ctx.db.visits
        .where('house_uuid')
        .equals(house.uuid)
        .first();

    if (existing && !isEditable(existing.status)) {
        throw new FieldValidationError(
            'This visit has been reviewed and can no longer be changed here.',
        );
    }

    const visit: LocalVisit = existing
        ? { ...existing, ...fields, sync: 'pending' }
        : {
              id: null,
              uuid: newUuid(),
              reference_code: null,
              version: 1,
              status: 'draft',
              sync: 'pending',
              house_uuid: house.uuid,
              ...fields,
          };

    await ctx.db.transaction('rw', ctx.db.visits, ctx.db.outbox, async () => {
        await ctx.db.visits.put(visit);
        await enqueue(ctx, 'visit', visit.uuid, 'upsert', {
            ...fields,
            house_uuid: house.uuid,
        });
    });

    return visit;
}

export async function createHousehold(
    ctx: FieldContext,
    house: LocalHouse,
    fields: HouseholdFields,
): Promise<LocalHousehold> {
    const visit = await ctx.db.visits
        .where('house_uuid')
        .equals(house.uuid)
        .first();

    if (visit && visit.occupancy_status !== 'occupied') {
        throw new FieldValidationError(
            'Record the house as occupied before adding households.',
        );
    }

    const household: LocalHousehold = {
        id: null,
        uuid: newUuid(),
        reference_code: null,
        version: 1,
        status: 'draft',
        sync: 'pending',
        house_uuid: house.uuid,
        sequence_in_house: null,
        review_note: null,
        secure: await seal(ctx.key, fields),
        updated_at: now(),
    };

    await ctx.db.transaction(
        'rw',
        ctx.db.households,
        ctx.db.outbox,
        async () => {
            await ctx.db.households.add(household);
            await enqueue(ctx, 'household', household.uuid, 'create', {
                ...fields,
                house_uuid: house.uuid,
            });
        },
    );

    return household;
}

export async function updateHousehold(
    ctx: FieldContext,
    household: LocalHousehold,
    fields: HouseholdFields,
): Promise<void> {
    if (!isEditable(household.status)) {
        throw new FieldValidationError(
            'This household has been submitted and cannot be changed in the field.',
        );
    }

    const secure = await seal(ctx.key, fields);

    await ctx.db.transaction(
        'rw',
        ctx.db.households,
        ctx.db.outbox,
        async () => {
            await ctx.db.households.update(household.uuid, {
                secure,
                sync: 'pending',
                updated_at: now(),
            });
            await enqueue(ctx, 'household', household.uuid, 'update', {
                ...fields,
            });
        },
    );
}

export async function readHousehold(
    ctx: FieldContext,
    household: LocalHousehold,
): Promise<HouseholdFields> {
    return open<HouseholdFields>(ctx.key, household.secure);
}

export async function activeOccupants(
    ctx: FieldContext,
    household: LocalHousehold,
): Promise<LocalOccupant[]> {
    return ctx.db.occupants
        .where('household_uuid')
        .equals(household.uuid)
        .filter((occupant) => occupant.status !== 'archived')
        .toArray();
}

async function assertSingleHead(
    ctx: FieldContext,
    household: LocalHousehold,
    relationship: string,
    exceptUuid: string | null,
): Promise<void> {
    if (relationship !== 'head') {
        return;
    }

    const others = (await activeOccupants(ctx, household)).filter(
        (occupant) =>
            occupant.relationship_to_head === 'head' &&
            occupant.uuid !== exceptUuid,
    );

    if (others.length > 0) {
        throw new FieldValidationError(
            'This household already has a head. Change the existing head first.',
        );
    }
}

export async function createOccupant(
    ctx: FieldContext,
    household: LocalHousehold,
    fields: OccupantFields,
): Promise<LocalOccupant> {
    if (!isEditable(household.status)) {
        throw new FieldValidationError(
            'This household has been submitted and cannot be changed in the field.',
        );
    }

    await assertSingleHead(ctx, household, fields.relationship_to_head, null);

    const occupant: LocalOccupant = {
        id: null,
        uuid: newUuid(),
        reference_code: null,
        version: 1,
        status: household.status,
        sync: 'pending',
        household_uuid: household.uuid,
        relationship_to_head: fields.relationship_to_head,
        secure: await seal(ctx.key, fields),
        updated_at: now(),
    };

    await ctx.db.transaction(
        'rw',
        ctx.db.occupants,
        ctx.db.outbox,
        async () => {
            await ctx.db.occupants.add(occupant);
            await enqueue(ctx, 'occupant', occupant.uuid, 'create', {
                ...fields,
                household_uuid: household.uuid,
            });
        },
    );

    return occupant;
}

export async function updateOccupant(
    ctx: FieldContext,
    household: LocalHousehold,
    occupant: LocalOccupant,
    fields: OccupantFields,
): Promise<void> {
    if (!isEditable(household.status)) {
        throw new FieldValidationError(
            'This household has been submitted and cannot be changed in the field.',
        );
    }

    await assertSingleHead(
        ctx,
        household,
        fields.relationship_to_head,
        occupant.uuid,
    );

    const secure = await seal(ctx.key, fields);

    await ctx.db.transaction(
        'rw',
        ctx.db.occupants,
        ctx.db.outbox,
        async () => {
            await ctx.db.occupants.update(occupant.uuid, {
                relationship_to_head: fields.relationship_to_head,
                secure,
                sync: 'pending',
                updated_at: now(),
            });
            await enqueue(ctx, 'occupant', occupant.uuid, 'update', {
                ...fields,
            });
        },
    );
}

export async function readOccupant(
    ctx: FieldContext,
    occupant: LocalOccupant,
): Promise<OccupantFields> {
    return open<OccupantFields>(ctx.key, occupant.secure);
}

/**
 * Removes a person entered by mistake. A record never sent to the server is
 * simply discarded; a synchronised one is archived on the server, never deleted.
 */
export async function removeOccupant(
    ctx: FieldContext,
    household: LocalHousehold,
    occupant: LocalOccupant,
): Promise<void> {
    if (!isEditable(household.status)) {
        throw new FieldValidationError(
            'This household has been submitted and cannot be changed in the field.',
        );
    }

    await ctx.db.transaction(
        'rw',
        ctx.db.occupants,
        ctx.db.outbox,
        async () => {
            const ops = await ctx.db.outbox
                .where('entity_uuid')
                .equals(occupant.uuid)
                .toArray();
            const neverSent =
                occupant.id === null && ops.every((op) => op.sent_at === null);

            if (neverSent) {
                await ctx.db.outbox.bulkDelete(ops.map((op) => op.seq!));
                await ctx.db.occupants.delete(occupant.uuid);

                return;
            }

            await ctx.db.occupants.update(occupant.uuid, {
                status: 'archived',
                sync: 'pending',
            });
            await enqueue(ctx, 'occupant', occupant.uuid, 'archive', {});
        },
    );
}

export async function submitHousehold(
    ctx: FieldContext,
    household: LocalHousehold,
): Promise<void> {
    if (!isEditable(household.status)) {
        throw new FieldValidationError('This household was already submitted.');
    }

    const occupants = await activeOccupants(ctx, household);

    if (occupants.length === 0) {
        throw new FieldValidationError(
            'Add at least one person before submitting the household.',
        );
    }

    if (
        occupants.filter((occupant) => occupant.relationship_to_head === 'head')
            .length !== 1
    ) {
        throw new FieldValidationError(
            'Exactly one person must be recorded as head of household.',
        );
    }

    await ctx.db.transaction(
        'rw',
        ctx.db.households,
        ctx.db.occupants,
        ctx.db.outbox,
        async () => {
            await ctx.db.households.update(household.uuid, {
                status: 'submitted',
                sync: 'pending',
            });
            await ctx.db.occupants
                .where('household_uuid')
                .equals(household.uuid)
                .filter((occupant) => occupant.status !== 'archived')
                .modify({ status: 'submitted' });
            await enqueue(ctx, 'household', household.uuid, 'submit', {});
        },
    );
}
