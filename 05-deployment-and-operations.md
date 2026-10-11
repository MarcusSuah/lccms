# LCCMS Phase 10: Deployment, Operations, Backup and Restore

This runbook is for the people who install and operate LCCMS. It assumes a single Linux application server with a separate MySQL server. The same steps apply to a managed platform such as Laravel Cloud, where the platform runs the queue worker, the scheduler and backups for you.

## 1. Requirements

| Component        | Version / note                                                                                     |
| ---------------- | -------------------------------------------------------------------------------------------------- |
| PHP              | 8.4 with `pdo_mysql`, `gd` (with WebP), `intl`, `mbstring`, `openssl`, `fileinfo`, `zip`, `bcmath` |
| MySQL            | 8.x (InnoDB, `utf8mb4`). CHECK constraints and generated columns are required.                     |
| Node.js and pnpm | Only on the build machine, for `pnpm run build`                                                    |
| Web server       | Nginx or Apache with HTTPS. HTTP must redirect to HTTPS.                                           |
| Process manager  | Supervisor or systemd for the queue worker                                                         |
| Cron             | One entry for the Laravel scheduler                                                                |

## 2. Environment

Copy `.env.example` to `.env` and set at least:

- `APP_ENV=production` and `APP_DEBUG=false`.
- `APP_URL=https://…`, the real HTTPS URL. Device tokens, signed links and the service worker depend on it.
- `APP_KEY`: generate it once with `php artisan key:generate`. **Store it in the password vault.** Contact details, correction payloads, conflict snapshots and two-factor secrets are encrypted with it. A database backup restored without the matching key loses those fields permanently.
- `DB_*`: a dedicated MySQL user with rights only on the LCCMS database.
- `SESSION_ENCRYPT=true` and `SESSION_SECURE_COOKIE=true`.
- `QUEUE_CONNECTION=database`. Use `redis` if Redis is available; Horizon is approved for that case but not installed yet.
- `MAIL_*`: needed for staff invitations, password resets and complaint updates.
- `LCCMS_*`: offline, upload, export and map limits. The defaults are documented in `config/lccms.php`. Set `LCCMS_MAP_TILE_URL` to a tile provider whose terms permit production traffic.

Never commit `.env`. Rotate the key with `APP_PREVIOUS_KEYS` (Laravel's graceful key rotation) rather than replacing `APP_KEY` outright.

## 3. First installation

```bash
composer install --no-dev --optimize-autoloader
pnpm install --frozen-lockfile && pnpm run build     # or build in CI and ship public/build
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan db:seed --class=ReferenceDataSeeder --force
php artisan lccms:create-super-admin admin@example.org "Goshen Community"
php artisan storage:link   # not required for private files; harmless
php artisan optimize
```

- Do **not** run `DevelopmentFixturesSeeder` in production. It refuses to run there and creates only `DEV-` test areas.
- Load official geography through **Geographic registry → Imports** (CSV) using the source approved by the census authority. LCCMS ships with no official place names.
- Two-factor authentication and passkeys are **off** by default (`LCCMS_STRONG_AUTHENTICATION=false`). When you set it to `true`, both appear under Settings → Security. Super and national administrators then have to enrol one of them before they can reach any admin page. Passkeys need HTTPS and an `APP_URL` that matches the domain users visit. Run `php artisan config:clear` (or `optimize`) after changing the setting.

## 4. Background processes

**Queue worker.** It runs report exports and notifications.

```ini
[program:lccms-queue]
command=php /srv/lccms/artisan queue:work --sleep=3 --tries=1 --timeout=620 --max-time=3600
user=www-data
autorestart=true
numprocs=1
```

The worker's `--timeout` must exceed the export job timeout of 600 seconds. Restart workers after every deployment with `php artisan queue:restart`.

**Scheduler.** Add one cron line:

```cron
* * * * * www-data cd /srv/lccms && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler currently runs `model:prune` for `ExportJob` once a day. It deletes export files older than the retention period and keeps the record of each export for the audit trail.

## 5. Web server notes

- Set the document root to `public/`. Deny everything else.
- Set the upload body limit (`client_max_body_size` in Nginx) to at least 30 MB, which covers five complaint photos of 5 MB plus overhead. Match PHP's `upload_max_filesize` (≥ 5M) and `post_max_size` (≥ 30M).
- `/field-sw.js` is served by Laravel with the right `Service-Worker-Allowed` scope. Do not cache it at a CDN or proxy, or updates to the field app will be delayed.
- The application sends its own security headers: `nosniff`, `X-Frame-Options: DENY`, Referrer-Policy, Permissions-Policy, and HSTS over HTTPS. Authenticated pages are sent with `Cache-Control: no-store`. Do not let a proxy override these.
- `storage/app/private` holds complaint photos, community media originals, leadership documents and exports. It must not be web-accessible. Files are only served through authorised routes.

## 6. Deploying a new release

1. Put the site in maintenance mode only if the release has migrations that lock large tables: `php artisan down --render="errors::503"`. Field devices keep working offline and will sync later.
2. Pull the release and run `composer install --no-dev --optimize-autoloader`. Ship the built `public/build`.
3. Take a fresh backup (§7). Then run `php artisan migrate --force`.
4. Run `php artisan optimize`, then `php artisan queue:restart`.
5. Run `php artisan up` and check `/up`. Sign in, open the dashboard and open one report.

## 7. Backups

What must be backed up, and how often:

| Item                                       | Method                                                            | Frequency                       | Retention (proposal, confirm with the census authority) |
| ------------------------------------------ | ----------------------------------------------------------------- | ------------------------------- | ------------------------------------------------------- |
| MySQL database                             | `mysqldump --single-transaction --routines --triggers --hex-blob` | Daily, plus before each release | 30 daily, 12 monthly                                    |
| `storage/app/private`                      | File sync (e.g. `restic`/`rsync`) excluding `exports/`            | Daily                           | Same as database                                        |
| `APP_KEY` (and `APP_PREVIOUS_KEYS`)        | Password vault, never stored next to the backups                  | On change                       | Forever                                                 |
| `.env` (without secrets) and server config | Configuration management                                          | On change                       | -                                                       |

Example nightly database job:

```bash
#!/usr/bin/env bash
set -euo pipefail
stamp=$(date +%F-%H%M)
mysqldump --single-transaction --routines --triggers --hex-blob \
  --default-character-set=utf8mb4 -h "$DB_HOST" -u "$DB_BACKUP_USER" -p"$DB_BACKUP_PASSWORD" "$DB_DATABASE" \
  | gzip | gpg --encrypt --recipient backups@example.org > "/backups/lccms-$stamp.sql.gz.gpg"
```

- Backups contain personal data. Encrypt them, as `gpg` does above, before they leave the server, and copy them off-site.
- Restrict who can read the backup store. Log access to it.
- Exports are temporary by design and are excluded.

## 8. Restore procedure (test it quarterly)

1. Provision an empty MySQL database and an application server with the **same `APP_KEY`** as the backup's environment.
2. Restore the database:
   `gpg --decrypt lccms-YYYY-MM-DD-HHMM.sql.gz.gpg | gunzip | mysql -h … -u … -p lccms`.
3. Restore `storage/app/private`.
4. Run `php artisan migrate --force` to apply any migrations newer than the backup. Then run `php artisan optimize`.
5. Verify the restore:
    - sign in as an administrator;
    - open a complaint with photos, which proves the files and decryption work;
    - open the population report for the latest campaign and compare its totals with the last known figures;
    - check that `php artisan queue:work --once` processes a test export.
6. Record the date, the backup used, the time taken and any problems in the operations log.

If `APP_KEY` is lost, encrypted fields cannot be recovered. Everything else, including census counts, the geography and the audit trail, is unaffected.

## 9. Monitoring and routine checks

- **Daily:**
    - failed jobs (`php artisan queue:failed`);
    - open sync conflicts (dashboard tile);
    - complaints marked urgent and still open (dashboard).
- **Weekly:**
    - the "Sync outcomes" report for workers with many rejected or retryable operations;
    - the "Supervisor workload" report for review backlogs;
    - devices that have not synced recently (Devices page).
- **Logs:** `storage/logs`. Personal data is not written to logs. Ship the logs to central storage if one is available.
- **Health:** `/up` returns 200 when the application boots.
- **Performance:**
    - turn on MySQL's slow query log (`long_query_time=1`);
    - report and dashboard queries run on composite indexes `(campaign_id, status, community_id|block_id)`;
    - if the public pages slow down under load, raise the cache lifetime for public figures, which are already cached until the next approval.

## 10. Incident response

- **Lost or stolen field device.** Revoke it on the Devices page. Its token stops working immediately. At its next contact the device is told to wipe. Its local data is encrypted under the enumerator's PIN.
- **Compromised staff account.**
    1. Deactivate the account under Users. Its sessions and tokens stop working at the next request.
    2. Review the activity log for the account.
    3. Reset its two-factor authentication.
- **Suspected data exposure.** The report export log (`export_jobs`, plus `reports` entries in the activity log) shows who exported what, with which filters and for what stated purpose.

## 11. Known limitations at handover

- Browser end-to-end tests (Playwright) are not installed. The offline network toggle has been verified manually, plus by unit tests of the local store and feature tests of the sync API.
- A Content-Security-Policy header is not set yet. Vite development mode and Inertia's inline page data need a nonce-based policy, which should be added and tested before go-live.
- Laravel Horizon is approved but not installed. It needs Redis.
- Census variables, consent wording, retention periods, the official geography source and the map tile provider are policy decisions for the census authority. See `04-security-and-governance.md` §8.
