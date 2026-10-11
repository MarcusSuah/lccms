import Dexie from 'dexie';
import type { EntityTable } from 'dexie';
import type { Sealed, VaultMeta } from './crypto';

/**
 * Local field database. IndexedDB is a working copy and an outbox, never the
 * authoritative census database. Personal data (names, dates of birth,
 * contacts, house coordinates) is stored only inside sealed (encrypted)
 * blobs; indexed columns hold identifiers and statuses only.
 */

export type RecordState = {
    /** Server id once synchronised. */
    id: number | null;
    uuid: string;
    reference_code: string | null;
    version: number;
    status: string;
    /** Local sync marker for the UI. */
    sync: 'synced' | 'pending' | 'failed' | 'conflict';
    sync_message?: string | null;
};

export type LocalBlock = {
    id: number;
    name: string;
    reference_code: string;
    community_id: number;
    community: string | null;
    town: string | null;
};

export type HouseFields = {
    local_house_number: string | null;
    building_type: string | null;
    tenure: string | null;
    floors: number | null;
    latitude: number | null;
    longitude: number | null;
    gps_accuracy_m: number | null;
    notes: string | null;
};

export type LocalHouse = RecordState & {
    block_id: number;
    local_house_number: string | null;
    building_type: string | null;
    secure: Sealed | null;
    updated_at: string;
};

export type VisitFields = {
    occupancy_status: string;
    visited_at: string | null;
    notes: string | null;
};

export type LocalVisit = RecordState & {
    house_uuid: string;
    occupancy_status: string;
    visited_at: string | null;
    notes: string | null;
};

export type HouseholdFields = {
    contact_name: string | null;
    contact_phone: string | null;
    residence_type: string;
};

export type LocalHousehold = RecordState & {
    house_uuid: string;
    sequence_in_house: number | null;
    review_note: string | null;
    secure: Sealed;
    updated_at: string;
};

export type OccupantFields = {
    full_name: string;
    date_of_birth: string | null;
    age_years: number | null;
    age_is_estimated: boolean;
    sex: string;
    relationship_to_head: string;
    usual_resident: boolean;
    school_attendance: string | null;
};

export type LocalOccupant = RecordState & {
    household_uuid: string;
    relationship_to_head: string;
    secure: Sealed;
    updated_at: string;
};

export type EntityType = 'house' | 'visit' | 'household' | 'occupant';

export type OutboxOperation = {
    op_uuid: string;
    seq?: number;
    campaign_id: number;
    entity_type: EntityType;
    entity_uuid: string;
    operation: string;
    /** Frozen at first send so retries hash identically on the server. */
    base_version: number | null;
    payload: Sealed;
    state: 'pending' | 'retry' | 'synced' | 'failed' | 'conflict';
    attempts: number;
    sent_at: string | null;
    next_attempt_at: string | null;
    last_error: string | null;
    created_at: string;
};

export type SyncHistoryEntry = {
    id?: number;
    at: string;
    sent: number;
    applied: number;
    rejected: number;
    conflicts: number;
    retryable: number;
    error: string | null;
};

export type MetaRow =
    | { key: 'vault'; value: VaultMeta }
    | { key: 'session'; value: Sealed }
    | { key: 'device'; value: DeviceInfo }
    | { key: 'package'; value: PackageInfo };

export type DeviceInfo = {
    device_uuid: string;
    user_id: number;
    user_name: string;
    offline_expires_at: string | null;
    registered_at: string;
};

export type PackageInfo = {
    campaign_id: number;
    campaign: {
        id: number;
        name: string;
        reference_code: string;
        status: string;
        reference_date: string;
        end_date: string;
        instructions: string | null;
    };
    options: Record<string, { value: string; label: string }[]>;
    generated_at: string;
};

export class FieldDatabase extends Dexie {
    meta!: EntityTable<MetaRow, 'key'>;
    blocks!: EntityTable<LocalBlock, 'id'>;
    houses!: EntityTable<LocalHouse, 'uuid'>;
    visits!: EntityTable<LocalVisit, 'uuid'>;
    households!: EntityTable<LocalHousehold, 'uuid'>;
    occupants!: EntityTable<LocalOccupant, 'uuid'>;
    outbox!: EntityTable<OutboxOperation, 'seq'>;
    history!: EntityTable<SyncHistoryEntry, 'id'>;

    constructor(name = 'lccms-field') {
        super(name);

        this.version(1).stores({
            meta: 'key',
            blocks: 'id, community_id',
            houses: 'uuid, id, block_id, sync',
            visits: 'uuid, id, house_uuid',
            households: 'uuid, id, house_uuid, status, sync',
            occupants: 'uuid, id, household_uuid, sync',
            outbox: '++seq, &op_uuid, state, entity_uuid',
            history: '++id, at',
        });
    }
}

let instance: FieldDatabase | null = null;

export function fieldDb(): FieldDatabase {
    instance ??= new FieldDatabase();

    return instance;
}

/**
 * Deletes the whole local database. Used on secure sign-out, device
 * revocation and after too many wrong PIN attempts.
 */
export async function wipeFieldDb(
    db: FieldDatabase = fieldDb(),
): Promise<void> {
    db.close();

    if (instance === db) {
        instance = null;
    }

    await Dexie.delete(db.name);
}
