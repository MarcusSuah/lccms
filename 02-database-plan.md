# LCCMS Phase 1: Database Plan (MySQL 8)

## Conventions

- Engine InnoDB, charset utf8mb4, collation utf8mb4_unicode_ci.
- Primary keys: `id` unsigned BIGINT. Offline-creatable tables (`houses`, `households`, `occupants`, `house_enumerations`) also carry `uuid` CHAR(36) UNIQUE (client-generated, stable idempotency key) and `reference_code` VARCHAR(40) UNIQUE (server-generated, human readable).
- Every foreign key is explicit, uses the matching `foreignId()` type, and uses **RESTRICT** on delete for official data. Nothing in census history cascades on delete.
- Lifecycle: `status` enum (`active|inactive|archived`) plus `deleted_at` only for non-official content (drafts, blog). Census records are archived, never deleted.
- Audit columns: `created_by`, `updated_by` (nullable FK users, restrict), timestamps.
- Concurrency: `version` UNSIGNED INT on records that can be edited offline.
- Data quality: `verification_status` enum (`unverified|verified|rejected`) on geography, facilities, infrastructure, leadership.

## Table groups

### Access

`users`, Spatie tables (`roles`, `permissions`, `model_has_roles`, ...), `user_geo_scopes` (user_id, `county_id` NOT NULL, `district_id … block_id` nullable FKs: the deepest non-null column is the scope; a CHECK requires a contiguous path and the resolver validates that it is consistent; starts_on, ends_on). Real FKs replace a polymorphic `scope_level/scope_id` pair, so a scope can never point at a deleted or wrong-level area. `devices` (user_id, device_uuid, label, last_seen_at, revoked_at, session_expires_at), `personal_access_tokens`.

### Geography

`counties`, `districts` (county_id, district_number, UNIQUE(county_id, district_number)), `clans` (district_id), `towns` (clan_id), `communities` (town_id), `blocks` (community_id). Common columns: reference_code UNIQUE, name, description, status, latitude/longitude DECIMAL(10,7) nullable, boundary_geojson JSON nullable (only verified data), verification_status, is_fixture. Name uniqueness is scoped to the parent: `UNIQUE(parent_id, normalized_name)`. Supporting: `geo_reassignments`, `geo_import_jobs`.

### Census

`census_campaigns` (reference_code, name, start_date, end_date, reference_date, status enum `draft|planned|active|in_review|closed|archived`), `campaign_scopes` (campaign_id + the same nullable path columns as `user_geo_scopes`), `campaign_assignments` (campaign_id, user_id, role enum `enumerator|supervisor`, supervisor_assignment_id nullable, path columns as above, starts_at, ends_at, status, expected_houses, reassigned_from_id), `houses` (block_id + denormalised ancestor ids, local_house_number, building_type, tenure, floors, lat/lng; `local_house_number` is nullable because informal settlements often lack numbers, and UNIQUE(block_id, local_house_number) applies when it is present), `house_enumerations` (house_id, campaign_id, enumerator_id, supervisor_id, occupancy_status, status, UNIQUE(house_id, campaign_id)), `households` (uuid, house_id, campaign_id, denormalised ancestor ids, reference_code, enumerator_id, supervisor_id, status, version, UNIQUE(id, campaign_id) as the target of the occupant composite FK), `occupants` (uuid, household_id, campaign_id, denormalised ancestor ids, full_name, date_of_birth, age_years, age_is_estimated, sex enum, relationship_to_head enum, usual_resident bool, school_attendance enum, status, version).

Record `status` enum for households and occupants: `draft → submitted → verified → approved`, with `rejected` and `archived`. Only `approved` rows enter official totals.

Constraints and integrity:

- No stored `household_size` or population totals. They are derived by aggregate queries (counts of approved occupants). Cached summaries live in `population_snapshots` (campaign_id, level, area_id, metric, value, computed_at) and are always rebuildable.
- `occupants.campaign_id` must equal its household's `campaign_id` (enforced by a composite FK `(household_id, campaign_id) → households(id, campaign_id)` using a unique index on households(id, campaign_id)).
- At most one head per household: generated column `head_marker = IF(relationship_to_head = 'head' AND status <> 'archived', household_id, NULL)` with a UNIQUE index. There is no `head_occupant_id` on households, which avoids a circular FK.
- Zero vs missing: counts distinguish `NULL` (not enumerated) from `0` through `house_enumerations.status` and `coverage_status`.
- History: `record_reviews` (reviewable polymorphic: action, from_status, to_status, note, actor), `record_corrections` (before/after JSON, reason, approver), `duplicate_flags` (occupant_id, matched_occupant_id, score, status `open|confirmed_distinct|merged_by_reviewer`).

### Offline synchronisation

`sync_batches` (batch_uuid, device_id, user_id, campaign_id, received_at, finished_at, item_count, status; UNIQUE(device_id, batch_uuid)), `sync_operations` (**op_uuid UNIQUE globally**: the client's outbox id, which is the real idempotency key, batch_id, entity_type, entity_uuid, operation enum `create|edit|correction|archive`, base_version, payload_hash, payload JSON (encrypted cast), status enum `applied|duplicate|rejected|conflict|retryable`, errors JSON, result_entity_id, applied_at). The key is per _operation_, not per batch: a client that rebuilds a batch under a new `batch_uuid` after a dropped response must still never create a second row. If the same `op_uuid` arrives with a different `payload_hash`, the operation is rejected as tampered or corrupt. `sync_conflicts` (sync_operation_id, entity_type, entity_id, server_version, base_version, conflict_class, server_snapshot JSON, submitted_snapshot JSON, status `open|resolved_server|resolved_client|resolved_merged`, resolved_by, resolved_at).

### Facilities

`facility_types` (code, category: school|hospital|clinic|health_other|market|toilet|other, ownership scope), `facilities` (community_id, block_id nullable, facility_type_id, ownership enum `public|private|ngo|faith|other`, operating_status, lat/lng, contacts JSON restricted, verification_status), type-specific tables `school_profiles`, `health_facility_profiles`, `market_profiles`, `sanitation_profiles` (1:1, facility_id PK), `facility_services`, `facility_inspections`. Verified absence is modelled in `community_service_assessments` (community_id, service_code, status `present|verified_absent|not_assessed`, assessed_on). A missing facility row alone never means "none".

### Leadership

`leadership_positions` (code, label, level `community|block`, configurable), `leadership_tenures` (position_id, community_id XOR block_id via CHECK, person_name, starts_on, ends_on, selection_method, verification_status, public_visible, exception_note, exception_approved_by). MySQL has no range-exclusion constraint, so overlap prevention lives in the `RecordLeadershipTenure` action. Inside a transaction it takes `SELECT … FOR UPDATE` on the existing tenures for (position, area) and rejects an overlap unless an approved exception (`exception_note`, `exception_approved_by`) is recorded. A generated `current_marker` column with a UNIQUE index also guarantees at most one open-ended tenure per position and area.

### Infrastructure and development

`infrastructure_assets`, `development_projects`, `project_milestones`, `project_progress_reports`.

### Public content

`media_items` (polymorphic owner: community, facility, infrastructure asset, event, post or house; disk, path, thumbnail_path, caption, sort_order, is_featured, visibility `public|internal|restricted`, approval_status, consent_status `not_required|obtained|pending|refused`, contains_minors bool). Public pages show only items that are `public`, approved, and not pending or refused consent, `events`, `posts`, `post_categories`, `tags`, `post_tag`, `announcements`, `contact_messages`, `pages` (about content).

### Complaints

`complaints` (public_reference, tracking_token_hash CHAR(64) UNIQUE, subject, category, description, county/community/block ids, house_reference nullable, complainant_name/contact nullable, is_anonymous, status, assigned_to), `complaint_attachments` (complaint_id, disk path, original_name_sanitised, mime, size, sha256; slot TINYINT). The maximum of 5 is enforced three ways: Form Request `max:5`, `UNIQUE(complaint_id, slot)` and `CHECK (slot BETWEEN 1 AND 5)`, so the database cannot hold a sixth row even under a race, `complaint_status_history`, `complaint_notes`.

### Platform

Audit uses the existing spatie `activity_log` table: causer, subject, event, and properties holding old/new values passed through a redactor. The redactor drops passwords and tokens, and for restricted models keeps only the names of changed PII fields; ip and user_agent go in properties. `import_jobs`, `import_row_errors`, `export_jobs` (requester, filters JSON, classification, purpose, status, file_path expiry), `notifications`, `system_settings`, `jobs`, `failed_jobs`, `cache`.

## Index plan

- Every FK column.
- Composite reporting indexes: `(campaign_id, status, block_id)` on households and occupants, `(campaign_id, community_id, status)` on occupants, `(county_id, status)` etc. for denormalised path columns.
- Sync lookups: `(device_id, batch_uuid)`, `sync_operations(op_uuid)`, `(entity_type, entity_uuid)`.
- Search: `(reference_code)`, `(parent_id, normalized_name)`, FULLTEXT on names.
- Complaints: `(status, assigned_to)`, `tracking_token_hash`.

## Reporting strategy

All totals come from SQL `COUNT/GROUP BY` against approved rows, filtered by the user's geographic scope in the same query. Roll-ups use the denormalised path columns so a county total is a single indexed aggregate, not a recursive join. Heavy reports run as queued jobs writing to `export_jobs`.
