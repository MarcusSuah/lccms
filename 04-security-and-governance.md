# LCCMS Phase 1: Security, Privacy and Governance Architecture

## 1. Geographic access control (core)

- `user_geo_scopes` holds the areas a user may act on, as FK path columns. A single `GeoScope` service converts a user's active scopes into path-column predicates (`county_id IN (...) OR district_id IN (...)`) and caches them per request. National roles hold an explicit `geo.national` permission rather than an implicit bypass.
- Scoped models use a `GeoScoped` trait that provides `->visibleTo($user)`, applied explicitly in controllers, reports, exports and sync. It is deliberately not an implicit global scope, because global scopes fail open in queues and console commands where no user is authenticated. Feature tests for every scoped endpoint assert cross-county denial. Policies call the same service for single-record checks, so a changed URL or ID returns 404/403, never data.
- The same service is used by: controllers/policies, report queries, export jobs, file download endpoints, sync endpoints, and the public content layer (public layer only reads approved publishable fields).
- Role = what you may do (Spatie permissions); scope = where you may do it. Both must pass.
- Frontend hides unauthorised actions for usability only.

## 2. Roles

The 15 roles from the master prompt are seeded with granular permissions (for example `occupants.view`, `occupants.export_pii`, `census.approve`, `sync.use`, `complaints.assign`). "Public Visitor" is a conceptual role: unauthenticated pages, no database role needed.

## 3. Authentication and sessions

The existing Fortify starter kit handles sessions for Inertia and already provides TOTP 2FA and passkeys. **Public self-registration is disabled**: administrators create staff accounts and invite them by email (set-password link plus email verification). Sanctum tokens for the offline API are device-bound, carry `sync:*` abilities, and expire. Rate limiting covers login, reset, sync and public forms. 2FA or a passkey is mandatory for Super Admin and National Admin (middleware redirects them to setup) and optional for other roles. Secure logout revokes tokens.

## 4. Data protection

- Classification: `public`, `internal`, `restricted` (household/occupant/PII), `sensitive-location` (house coordinates).
- Data minimisation: occupant fields limited to the approved methodology list; no national ID fields by default.
- Public site: reads only approved aggregates and approved public content; never occupants, household composition, private contacts, or house coordinates.
- Output safety: React escapes by default; rich text sanitised server-side (HTMLPurifier via `mews/purifier`, pending dependency approval) before storage.
- Mass assignment: explicit `$fillable` and Form Requests; IDOR protection through the geo scope; SQL injection avoided by the query builder and bound parameters.
- Exports: permission + scope + purpose recorded in `export_jobs`; PII exports need `occupants.export_pii` and a stated purpose; files in private disk with expiry and signed, authorised download.
    - _As built:_ report definitions (`app/Services/Reports`) produce the same rows for the screen and for exports. They run as the requesting user, so an export can never hold more than that user can see.
    - Exporting needs `reports.export`. The occupant list also needs `census.view-personal-data` to view it, and `reports.export-personal-data` plus a stated purpose of at least 10 characters to export it.
    - Viewing a personal-data report is recorded in the activity log. So is every download.
    - Files are stored as `storage/app/private/exports/{uuid}.{ext}`. Only the requester can download them, and they are pruned after `LCCMS_EXPORT_RETENTION_DAYS`. The `export_jobs` row is kept as the audit record.
    - CSV and Excel cells cannot carry spreadsheet formulas. CSV text that starts with `= + - @` is prefixed with an apostrophe, and Excel cells are written with explicit types.
- Maps: house coordinates (`sensitive-location`) appear only to users with `census.view-personal-data`, within their reach. The public map shows only published, verified communities and public, verified facilities.

## 5. File handling

Complaint images and census documents go to a private disk and are served only through an authorised controller. Validate real content (finfo MIME, full image decode via GD/Intervention, a pixel-dimension cap against decompression bombs), allowed formats (jpeg, png, webp), size limit from config, re-encode to strip EXIF/GPS and executable payloads, random server-side filenames (original name stored only as sanitised display text), hard maximum of 5 per complaint in the Form Request and in the database slot constraint.

## 6. Auditability

The spatie activitylog (`activity_log` table) records create, edit, verify, approve, correct, export, archive, reassign, login/permission changes. Values pass through a redactor (no passwords, tokens or unnecessary personal data). Logs are append-only at the application level and monitored.

## 7. Operations

Secrets only in environment; production `APP_DEBUG=false`; HTTPS and HSTS; queue and failed-job monitoring (Horizon); slow-query log; daily encrypted MySQL backups off-server with a documented, tested restore procedure; retention and archival policy configurable in `system_settings`.

_As built:_

- The `SecurityHeaders` middleware sends these headers on every web and API response:
    - `X-Content-Type-Options: nosniff`;
    - `X-Frame-Options: DENY`;
    - `Referrer-Policy: strict-origin-when-cross-origin`;
    - a Permissions-Policy that allows geolocation for this origin only;
    - `Cross-Origin-Opener-Policy: same-origin`;
    - HSTS over HTTPS.
- Authenticated pages get `Cache-Control: no-store, private`.
- The starter kit's unused `GET /api/user` endpoint was removed, so device tokens reach only the versioned field API.
- A Content-Security-Policy is still to do (see `05-deployment-and-operations.md` §11).
- The backup and restore procedure is in `05-deployment-and-operations.md`.

## 8. Items requiring your decision before production (not invented here)

- Legal basis, consent wording and retention periods under applicable Liberian law and the authorising census body.
- Approved census variables (the occupant field list is a configurable proposal).
- Official geographic reference data source and map tile provider.
- Email/SMS provider credentials.
