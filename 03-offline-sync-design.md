# LCCMS Phase 1: Offline Field Enumeration and Synchronisation Design

## 1. Client architecture

- **PWA shell** (vite-plugin-pwa service worker) caches the app so the enumerator UI loads with no network.
- **IndexedDB via Dexie** is the structured store: `assignment_packages`, `geo_reference`, `drafts_houses`, `drafts_households`, `drafts_occupants`, `outbox` (queued operations), `sync_history`, `meta`.
- **Outbox pattern.** Every create/edit/correction/archive request appends an immutable operation `{op_id (uuid), entity_type, entity_uuid, operation, base_version, payload, created_at}` to `outbox`. Drafts are derived state. Closing the browser loses nothing, because writes are committed to IndexedDB before the UI confirms.
- Temporary identifiers are client UUIDv4s. The server later attaches its own `reference_code`; the UUID stays as the idempotency key.
- GPS via the Geolocation API when permitted. Never fabricated.

## 2. Offline session and device security

Threat model (lost or stolen device, shared device, malware, shoulder surfing, tampered client):

| Threat                                | Mitigation                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| ------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Stolen or lost device with local data | Data encrypted at rest with AES-GCM (Web Crypto). A random 256-bit data key is wrapped (AES-KW) by a key derived with PBKDF2-SHA256 (Web Crypto, at least 600k iterations, no extra dependency) from the enumerator's **offline PIN** plus a per-device random salt. The wrapped key lives in IndexedDB. The PIN and the unwrapped key never do: the key exists only in memory, as a non-extractable `CryptoKey`, while the app is unlocked. The Sanctum device token is also stored encrypted under this key. Five wrong PINs wipe the wrapped key, which makes any unsynced data unrecoverable, and the UI warns about this before the final attempt. No reusable secret ships in the JS bundle. |
| Offline session abuse                 | Server issues a **short-lived offline grant** (configurable, default 72 h, max set in `system_settings`) bound to user + device. After expiry the app locks, and unsynced data stays encrypted until the user re-authenticates online.                                                                                                                                                                                                                                                                                                                                                                                                                                                             |
| Device revoked                        | `devices.revoked_at` is checked at every sync and token refresh; a revoked device is rejected and told to wipe local data on next contact.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| Over-collection                       | Only assigned areas are downloaded; only minimum fields for existing records (reference data + names only where a revisit needs them), never national IDs.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| Tampered client                       | The server trusts nothing: every item is re-validated, scope-checked and version-checked.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |

Limitations (documented for users): browser storage can be cleared by the user or OS; clearing local data does **not** erase copies in OS backups or forensic images; encryption only protects against someone without the PIN; a PIN that is weak or shared weakens everything; a compromised device or browser can read data while the app is unlocked. Device requirements: screen lock, current OS, no shared accounts, report loss immediately so an administrator can revoke the device.
Secure logout encrypts-then-wipes: if unsynced items exist the user is warned; "wipe" deletes the IndexedDB database, Cache Storage, and the wrapped key.

## 3. Assignment package download

`GET /api/v1/offline/packages/{campaign}`: Sanctum token + device check. Returns only the assigned scope: geography subtree, reference enums, existing houses/households (minimised) for the scope, and a `package_version` + `server_time`. Delta refresh via `?since=`.

## 4. Synchronisation API (versioned, /api/v1)

- `POST /api/v1/sync/batches` body: `{batch_uuid, device_uuid, campaign_id, operations[≤100]: {op_uuid, entity_type, entity_uuid, operation, base_version, parent_uuid, payload}}`.
- `GET /api/v1/sync/batches/{batch_uuid}` returns stored per-item results (lets a client recover after a dropped response).
- `POST /api/v1/devices/register`, `DELETE /api/v1/devices/{uuid}` (revoke).
- `GET /api/v1/sync/conflicts` for the enumerator's own conflicts.

Processing rules (in `ProcessSyncBatch` action):

1. Authenticate (Sanctum) and check device not revoked and the grant not expired.
2. Check batch size ≤ configured max and payload size limits. Rate limit per user/device.
3. **Idempotency.** `UNIQUE(device_id, batch_uuid)`: if the batch exists, return its stored results unchanged. Per operation, a globally unique `op_uuid` means a replayed operation returns its original stored result, even inside a rebuilt batch with a new `batch_uuid`. A `create` for an `entity_uuid` that already exists (`UNIQUE(uuid)` on the entity table) returns `duplicate` with the existing server reference, never a second row. The unique constraints are the final guard when two concurrent retries race.
4. For each item, in its own transaction (a bad item must not poison the batch): validate with Form-Request-style rules, confirm the campaign is `active` and the user holds an active assignment covering the item's block, confirm parent-child integrity (occupant → household → house → block → assigned scope), confirm the item belongs to the enumerator (cannot edit another enumerator's restricted records).
5. Operations:
    - `create`: insert as `draft/submitted`; server assigns `reference_code`; totals unaffected until approval.
    - `edit`: requires `base_version`; if `base_version == server.version` apply and increment; otherwise conflict.
    - `correction`: only on submitted/verified rows or via the correction workflow; finalized (approved) rows are never edited by sync. A correction request is recorded for authorised review.
    - `archive`: soft archival request; never a hard delete.
6. Per-item result: `applied | duplicate | rejected(errors[]) | conflict(conflict_id) | retryable(reason)`. HTTP 200 for the batch (partial success is normal), 4xx only for batch-level failures (auth, size, revoked device).
7. Log outcomes in `sync_operations`, increment metrics, write audit entries (no PII in logs).
8. Retryable = transient (lock timeout, deadlock). The client keeps these in the outbox with backoff.

## 5. Conflict handling

Classes: `edit_edit` (both changed same record), `edit_after_review` (server record moved to verified/approved/rejected), `parent_missing` (parent house archived or reassigned), `duplicate_suspected`, `scope_changed` (assignment revoked after offline work). Behaviour: **never overwrite**; store `server_snapshot` and `submitted_snapshot` in `sync_conflicts`; supervisor resolves (keep server, accept client, or merge) with a reason; resolution is audited and bumps the version. The enumerator sees conflicted items in the draft queue.

## 6. Approval and totals

Sync can only produce `draft/submitted` data. `submitted → verified → approved` is performed by supervisors and administrators in the review workflow. Dashboards report registered, submitted, verified and approved counts separately; official totals use approved only.

## 7. Client sync loop

Online event or manual button triggers: lock outbox → build batches (≤ batch limit, ordered by dependency: house → household → occupant) → POST → apply per-item results (mark synced, failed, conflicted) → write `sync_history` → refresh package delta. Exponential backoff with jitter on network or 5xx. Safe to interrupt at any point.

## 8. Acceptance scenarios and how they will be proven

| #   | Scenario                                  | Test                                                                                                    |
| --- | ----------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| 1   | Offline house + several households        | Vitest + fake-indexeddb for the client store (dependency approval pending), plus Pest sync feature test |
| 2   | Several occupants per household           | same                                                                                                    |
| 3   | Close/reopen, drafts survive              | Vitest persistence test and Playwright reload test                                                      |
| 4   | Syncs when connectivity returns           | Playwright with network toggle                                                                          |
| 5   | Retry same batch, no duplicates           | Pest: post identical batch twice, assert row counts                                                     |
| 6   | Invalid/unauthorised rejected with errors | Pest: out-of-scope block, wrong campaign, revoked device                                                |
| 7   | Conflicting edits preserved               | Pest: two clients, stale `base_version`                                                                 |
| 8   | Totals change only on approval            | Pest: aggregate before/after review                                                                     |

Offline completeness will not be claimed until these pass.

## 9. Implementation notes (as built)

Where the build differs from the plan above, the build is authoritative.

- **Service worker.** A small hand-written worker served at `/field-sw.js` (`Field\ServiceWorkerController`) instead of vite-plugin-pwa, so no extra dependency was needed. It caches only the `/field` shell and the hashed build assets it references. Admin pages and API responses are never cached.
- **Local store.** Dexie database `lccms-field` with tables `meta`, `blocks`, `houses`, `visits`, `households`, `occupants`, `outbox` (`++seq, &op_uuid`) and `history`. Personal fields are sealed with the in-memory AES-GCM key before they are written. Crypto calls inside Dexie transactions are wrapped in `Dexie.waitFor` so the transaction does not auto-commit while a Web Crypto promise is pending.
- **PIN and wipe.** PBKDF2-SHA256 derives a wrapping key from the PIN and a per-device salt. That key unwraps (AES-KW) a random AES-GCM data key that is held only in memory. Five wrong PINs call `wipeFieldDb()`, which deletes the IndexedDB database, Cache Storage and the wrapped key.
- **Endpoints actually built:**
    - `GET /api/v1/field/session` returns the grant, the user and the current assignments.
    - `GET /api/v1/field/campaigns/{campaign}/package` returns the assignment package.
    - `POST /api/v1/sync/batches` and `GET /api/v1/sync/batches/{batchUuid}` submit a batch and let a client recover its stored results.
    - `GET /api/v1/sync/conflicts` lists the device user's open conflicts.
    - `DELETE /api/v1/field/device` revokes the device.
    - `GET /api/v1/devices/{uuid}/status` is unauthenticated and throttled. It lets a revoked device learn that it must wipe.
    - Device registration happens from the signed-in web session at `POST /field/devices`, which issues the Sanctum token.
- **Idempotency.** `sync_operations.op_uuid` is globally unique. A replayed operation returns its stored result if the payload hash matches, and is rejected if the same `op_uuid` arrives with a different payload. Entity `uuid` columns are unique, so a repeated `create` returns `duplicate` with the existing reference.
- **Batch processing.** `SyncBatchProcessor` runs each operation in its own transaction. A child that arrives before its parent is reported `retryable` (`ParentPendingException`), and the client resends it after the parent is applied.
- **Conflict classes implemented:** `edit_edit`, `edit_after_review` and `scope_changed`. The submitted payload and the server snapshot are stored encrypted in `sync_conflicts`. A supervisor keeps the server version, applies the client version (bumping `version`), or converts the conflict to a correction request. Every resolution is audited.
- **Limits** (in `config/lccms.php`, `offline`):
    - 100 operations and 512 KB per batch;
    - a 72-hour offline grant;
    - device tokens that last 30 days;
    - at most 3 active devices per user;
    - per-user rate limiting through the `field-sync` limiter.
- **Proof.**
    - Scenarios 1–3 are covered by Vitest with fake-indexeddb (`resources/js/offline/offline.test.ts`).
    - Scenarios 5–8 are covered by Pest (`tests/Feature/Api/V1/FieldSyncTest.php` and `tests/Feature/Census`).
    - Scenario 4, a real network toggle, still needs a browser end-to-end test (Playwright is not installed). Until then, it is verified manually by switching the device offline and online.
- **Monitoring.** The "Sync outcomes and conflicts" report (`/admin/reports/sync-outcomes`) shows, per field worker, how many operations were applied, deduplicated, rejected, held as conflicts or retried.
