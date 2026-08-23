# Production deploy

Do not deploy without backups. Do not commit real credentials.

## Required production environment

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

HTTPS is forced when `APP_ENV=production`. Put the app behind TLS. Set `APP_KEY` and database credentials on the server only.

Session cookies: `httpOnly` on, `SameSite=lax` (private-link GET grant then same-site form posts). Lifetime 120 minutes. Database sessions are swept by Laravel's lottery (`2/100` requests). Do not set `SameSite=none` unless you also use `Secure`.

## Backup before deploy

- database dump
- `.env` copy
- `storage/` if receipts or other files exist

## Deploy checklist

1. Backup database, `.env`, and storage.
2. `php artisan down` if the host supports maintenance mode.
3. Deploy code (`git pull` or host upload).
4. `composer install --no-dev --optimize-autoloader`
5. `php artisan migrate --force` (additive create-table migrations; MySQL index names are shortened to stay under 64 characters)
6. `php artisan optimize:clear`
7. `php artisan config:cache`
8. `php artisan route:cache`
9. `php artisan view:cache`
10. Confirm `storage/` and `bootstrap/cache` are writable.
11. Queue worker is not required for the current request/response flow.
12. Scheduler: cron `* * * * * php artisan schedule:run` so `finance:recurring-run` posts due recurring transactions daily.
13. Smoke: admin login, one private link, one FAMILY expense, one BUSINESS income, dashboard saldo.

## Private link / access-log risk

The grant URL is `/access/{64-hex-token}`. The plaintext token is not stored in the database (SHA-256 hash only). Session stores `token_id` only; the grant URL is stripped from session previous/intended URLs so the token is not persisted there. Web-server access logs can still record the path.

Production mitigations:

- HTTPS only; `SESSION_SECURE_COOKIE=true`
- `Referrer-Policy: no-referrer` on the grant redirect
- expire and revoke tokens; regenerate after suspected leak
- restrict access-log retention; do not ship logs that contain `/access/` to untrusted systems
- treat the link as a credential when sending it to family/business users

## Rollback

- Code: redeploy the previous git revision.
- Config: restore the `.env` backup.
- Schema migrations in this project are additive (tables/columns). Rolling back a migration that already created production data is usually the wrong fix; restore the database backup taken before `migrate --force`.
- Do not run `migrate:fresh`, `migrate:reset`, or truncate on production.
- Ownership/account backfill commands are write-once style. If they were run, restore from the pre-command backup rather than inventing reversing edits.

## Audit after deploy

```
php artisan finance:entity-audit
php artisan finance:account-audit
php artisan finance:balance-audit
php artisan finance:audit-log-check
php artisan finance:legacy-cleanup-check
```

Critical counts must stay 0. Do not insert balancing rows to hide a mismatch.
