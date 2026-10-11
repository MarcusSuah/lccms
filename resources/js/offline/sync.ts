import type { FieldApi } from './api';
import { open, seal } from './crypto';
import type {
    EntityType,
    FieldDatabase,
    LocalBlock,
    OutboxOperation,
    PackageInfo,
    RecordState,
    SyncHistoryEntry,
} from './db';

/**
 * Moves queued operations to the server and applies the per-operation
 * results. Safe to interrupt at any point: an operation keeps its op_uuid and
 * frozen base_version, so a resend is recognised by the server.
 */

export type SyncResult = {
    op_uuid: string;
    entity_type?: EntityType;
    entity_uuid?: string;
    status: 'applied' | 'duplicate' | 'rejected' | 'conflict' | 'retryable';
    code?: string;
    errors?: Record<string, string[]>;
    record?: {
        id?: number;
        uuid?: string;
        reference_code?: string;
        version?: number;
        status?: string;
    };
    conflict_id?: number;
    replayed?: boolean;
};

export type SyncReport = {
    sent: number;
    applied: number;
    rejected: number;
    conflicts: number;
    retryable: number;
    remaining: number;
};

const MAX_ROUNDS = 20;

export function backoffMs(attempts: number): number {
    const base = Math.min(15 * 60_000, 2 ** attempts * 5_000);

    return base / 2 + Math.random() * (base / 2);
}

function table(db: FieldDatabase, entityType: EntityType) {
    switch (entityType) {
        case 'house':
            return db.houses;
        case 'visit':
            return db.visits;
        case 'household':
            return db.households;
        case 'occupant':
            return db.occupants;
    }
}

/**
 * Picks the next batch: oldest first, at most one operation per record, and
 * nothing for a record whose earlier operation is still waiting.
 */
export async function nextBatch(
    db: FieldDatabase,
    max: number,
): Promise<OutboxOperation[]> {
    const nowIso = new Date().toISOString();
    const queued = await db.outbox
        .orderBy('seq')
        .filter((op) => op.state === 'pending' || op.state === 'retry')
        .toArray();

    const blocked = new Set<string>();
    const picked: OutboxOperation[] = [];

    for (const op of queued) {
        if (picked.length >= max) {
            break;
        }

        if (blocked.has(op.entity_uuid)) {
            continue;
        }

        blocked.add(op.entity_uuid);

        if (
            op.state === 'retry' &&
            op.next_attempt_at &&
            op.next_attempt_at > nowIso
        ) {
            continue;
        }

        picked.push(op);
    }

    return picked;
}

async function freezeBaseVersions(
    db: FieldDatabase,
    ops: OutboxOperation[],
): Promise<void> {
    const sentAt = new Date().toISOString();

    for (const op of ops) {
        const changes: Partial<OutboxOperation> = {
            sent_at: op.sent_at ?? sentAt,
        };

        if (op.base_version === null && op.operation !== 'create') {
            const record = (await table(db, op.entity_type).get(
                op.entity_uuid,
            )) as RecordState | undefined;

            if (op.operation === 'upsert' && (record?.id ?? null) === null) {
                changes.base_version = null;
            } else {
                changes.base_version = record?.version ?? 1;
            }

            op.base_version = changes.base_version ?? null;
        }

        op.sent_at = changes.sent_at ?? null;
        await db.outbox.update(op.seq!, changes);
    }
}

async function applyResult(
    db: FieldDatabase,
    op: OutboxOperation,
    result: SyncResult,
): Promise<void> {
    const records = table(db, op.entity_type);

    await db.transaction('rw', db.outbox, records, async () => {
        if (result.status === 'applied' || result.status === 'duplicate') {
            await db.outbox.update(op.seq!, {
                state: 'synced',
                last_error: null,
            });

            const stillQueued = await db.outbox
                .where('entity_uuid')
                .equals(op.entity_uuid)
                .filter(
                    (other) =>
                        other.state === 'pending' || other.state === 'retry',
                )
                .count();

            const changes: Partial<RecordState> = {
                sync: stillQueued > 0 ? 'pending' : 'synced',
                sync_message: null,
            };

            if (result.record?.id !== undefined) changes.id = result.record.id;
            if (result.record?.reference_code !== undefined)
                changes.reference_code = result.record.reference_code;
            if (result.record?.version !== undefined)
                changes.version = result.record.version;
            if (result.record?.status !== undefined)
                changes.status = result.record.status;

            await records.update(op.entity_uuid, changes);

            return;
        }

        if (result.status === 'retryable') {
            const attempts = op.attempts + 1;
            await db.outbox.update(op.seq!, {
                state: 'retry',
                attempts,
                last_error:
                    Object.values(result.errors ?? {}).flat()[0] ??
                    result.code ??
                    null,
                next_attempt_at: new Date(
                    Date.now() + backoffMs(attempts),
                ).toISOString(),
            });

            return;
        }

        const message =
            result.status === 'conflict'
                ? 'Changed on the server since this device copy. A supervisor will decide which version to keep.'
                : Object.values(result.errors ?? {})
                      .flat()
                      .join(' ') ||
                  result.code ||
                  'Rejected by the server.';

        await db.outbox.update(op.seq!, {
            state: result.status === 'conflict' ? 'conflict' : 'failed',
            last_error: message,
        });
        await records.update(op.entity_uuid, {
            sync: result.status === 'conflict' ? 'conflict' : 'failed',
            sync_message: message,
        });
    });
}

/**
 * Sends every eligible queued operation, round by round, until nothing more
 * can make progress. Throws OfflineError / DeviceRejectedError from the API.
 */
export async function syncOutbox(
    db: FieldDatabase,
    key: CryptoKey,
    api: FieldApi,
    maxBatch: number,
): Promise<SyncReport> {
    const report: SyncReport = {
        sent: 0,
        applied: 0,
        rejected: 0,
        conflicts: 0,
        retryable: 0,
        remaining: 0,
    };

    for (let round = 0; round < MAX_ROUNDS; round++) {
        const ops = await nextBatch(db, maxBatch);

        if (ops.length === 0) {
            break;
        }

        await freezeBaseVersions(db, ops);

        const byCampaign = new Map<number, OutboxOperation[]>();

        for (const op of ops) {
            byCampaign.set(op.campaign_id, [
                ...(byCampaign.get(op.campaign_id) ?? []),
                op,
            ]);
        }

        let progressed = false;

        for (const [campaignId, campaignOps] of byCampaign) {
            const operations = await Promise.all(
                campaignOps.map(async (op) => ({
                    op_uuid: op.op_uuid,
                    entity_type: op.entity_type,
                    entity_uuid: op.entity_uuid,
                    operation: op.operation,
                    base_version: op.base_version,
                    payload: await open<Record<string, unknown>>(
                        key,
                        op.payload,
                    ),
                })),
            );

            const response = await api.post<{ results: SyncResult[] }>(
                '/api/v1/sync/batches',
                {
                    batch_uuid: crypto.randomUUID(),
                    campaign_id: campaignId,
                    operations,
                },
            );

            report.sent += campaignOps.length;

            for (const op of campaignOps) {
                const result = response.results.find(
                    (candidate) => candidate.op_uuid === op.op_uuid,
                );

                if (!result) {
                    continue;
                }

                await applyResult(db, op, result);

                if (
                    result.status === 'applied' ||
                    result.status === 'duplicate'
                ) {
                    report.applied++;
                    progressed = true;
                } else if (result.status === 'retryable') {
                    report.retryable++;
                } else if (result.status === 'conflict') {
                    report.conflicts++;
                    progressed = true;
                } else {
                    report.rejected++;
                    progressed = true;
                }
            }
        }

        if (!progressed) {
            break;
        }
    }

    report.remaining = await db.outbox
        .filter((op) => op.state === 'pending' || op.state === 'retry')
        .count();

    const entry: SyncHistoryEntry = {
        at: new Date().toISOString(),
        sent: report.sent,
        applied: report.applied,
        rejected: report.rejected,
        conflicts: report.conflicts,
        retryable: report.retryable,
        error: null,
    };
    await db.history.add(entry);

    return report;
}

type PackageResponse = {
    campaign: PackageInfo['campaign'];
    blocks: LocalBlock[];
    houses: {
        id: number;
        uuid: string;
        reference_code: string;
        block_id: number;
        local_house_number: string | null;
        building_type: string | null;
        tenure: string | null;
        floors: number | null;
        version: number;
    }[];
    visits: {
        id: number;
        uuid: string;
        house_id: number;
        occupancy_status: string;
        status: string;
        visited_at: string | null;
        notes: string | null;
        version: number;
    }[];
    households: {
        id: number;
        uuid: string;
        reference_code: string;
        house_id: number;
        sequence_in_house: number;
        contact_name: string | null;
        contact_phone: string | null;
        residence_type: string;
        status: string;
        review_note: string | null;
        version: number;
    }[];
    occupants: {
        id: number;
        uuid: string;
        reference_code: string;
        household_id: number;
        full_name: string;
        date_of_birth: string | null;
        age_years: number | null;
        age_is_estimated: boolean;
        sex: string;
        relationship_to_head: string;
        usual_resident: boolean;
        school_attendance: string | null;
        status: string;
        version: number;
    }[];
    options: PackageInfo['options'];
    generated_at: string;
};

/**
 * Downloads the assignment package (or the changes since the last one) and
 * merges it. Local records with unsent changes are never overwritten.
 */
export async function refreshPackage(
    db: FieldDatabase,
    key: CryptoKey,
    api: FieldApi,
    campaignId: number,
): Promise<PackageInfo> {
    const previous = (await db.meta.get('package'))?.value as
        | PackageInfo
        | undefined;
    const since =
        previous && previous.campaign_id === campaignId
            ? `?since=${encodeURIComponent(new Date(new Date(previous.generated_at).getTime() - 5 * 60_000).toISOString())}`
            : '';
    const pkg = await api.get<PackageResponse>(
        `/api/v1/field/campaigns/${campaignId}/package${since}`,
    );

    const sealedHouseholds = await Promise.all(
        pkg.households.map(async (household) => ({
            household,
            secure: await seal(key, {
                contact_name: household.contact_name,
                contact_phone: household.contact_phone,
                residence_type: household.residence_type,
            }),
        })),
    );
    const sealedOccupants = await Promise.all(
        pkg.occupants.map(async (occupant) => ({
            occupant,
            secure: await seal(key, {
                full_name: occupant.full_name,
                date_of_birth: occupant.date_of_birth,
                age_years: occupant.age_years,
                age_is_estimated: occupant.age_is_estimated,
                sex: occupant.sex,
                relationship_to_head: occupant.relationship_to_head,
                usual_resident: occupant.usual_resident,
                school_attendance: occupant.school_attendance,
            }),
        })),
    );

    await db.transaction(
        'rw',
        [db.meta, db.blocks, db.houses, db.visits, db.households, db.occupants],
        async () => {
            const isClean = async (
                tableName: 'houses' | 'visits' | 'households' | 'occupants',
                uuid: string,
            ) => {
                const local = (await db[tableName].get(uuid)) as
                    | RecordState
                    | undefined;

                return !local || local.sync === 'synced';
            };

            await db.blocks.bulkPut(pkg.blocks);

            const houseUuidById = new Map<number, string>();

            for (const house of pkg.houses) {
                houseUuidById.set(house.id, house.uuid);

                if (await isClean('houses', house.uuid)) {
                    const existing = await db.houses.get(house.uuid);
                    await db.houses.put({
                        id: house.id,
                        uuid: house.uuid,
                        reference_code: house.reference_code,
                        version: house.version,
                        status: 'active',
                        sync: 'synced',
                        block_id: house.block_id,
                        local_house_number: house.local_house_number,
                        building_type: house.building_type,
                        secure: existing?.secure ?? null,
                        updated_at: new Date().toISOString(),
                    });
                }
            }

            const lookupHouseUuid = async (id: number) =>
                houseUuidById.get(id) ??
                (await db.houses.where('id').equals(id).first())?.uuid;

            for (const visit of pkg.visits) {
                const houseUuid = await lookupHouseUuid(visit.house_id);

                if (houseUuid && (await isClean('visits', visit.uuid))) {
                    await db.visits.put({
                        id: visit.id,
                        uuid: visit.uuid,
                        reference_code: null,
                        version: visit.version,
                        status: visit.status,
                        sync: 'synced',
                        house_uuid: houseUuid,
                        occupancy_status: visit.occupancy_status,
                        visited_at: visit.visited_at,
                        notes: visit.notes,
                    });
                }
            }

            const householdUuidById = new Map<number, string>();

            for (const { household, secure } of sealedHouseholds) {
                householdUuidById.set(household.id, household.uuid);
                const houseUuid = await lookupHouseUuid(household.house_id);

                if (
                    houseUuid &&
                    (await isClean('households', household.uuid))
                ) {
                    await db.households.put({
                        id: household.id,
                        uuid: household.uuid,
                        reference_code: household.reference_code,
                        version: household.version,
                        status: household.status,
                        sync: 'synced',
                        house_uuid: houseUuid,
                        sequence_in_house: household.sequence_in_house,
                        review_note: household.review_note,
                        secure,
                        updated_at: new Date().toISOString(),
                    });
                }
            }

            for (const { occupant, secure } of sealedOccupants) {
                const householdUuid =
                    householdUuidById.get(occupant.household_id) ??
                    (
                        await db.households
                            .where('id')
                            .equals(occupant.household_id)
                            .first()
                    )?.uuid;

                if (
                    householdUuid &&
                    (await isClean('occupants', occupant.uuid))
                ) {
                    await db.occupants.put({
                        id: occupant.id,
                        uuid: occupant.uuid,
                        reference_code: occupant.reference_code,
                        version: occupant.version,
                        status: occupant.status,
                        sync: 'synced',
                        household_uuid: householdUuid,
                        relationship_to_head: occupant.relationship_to_head,
                        secure,
                        updated_at: new Date().toISOString(),
                    });
                }
            }

            await db.meta.put({
                key: 'package',
                value: {
                    campaign_id: campaignId,
                    campaign: pkg.campaign,
                    options: pkg.options,
                    generated_at: pkg.generated_at,
                },
            });
        },
    );

    return {
        campaign_id: campaignId,
        campaign: pkg.campaign,
        options: pkg.options,
        generated_at: pkg.generated_at,
    };
}
