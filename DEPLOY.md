# Deployment — Finance plantation events (Task 7)

See `plantation-management/DEPLOY.md` for the ordered rollout.

Finance is deployed **first**. Keep Plantation `INTEGRATION_EVENTS_ENABLED=false` until this receiver is live.

## Commands after deploy

```
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan finance:entity-audit
php artisan finance:account-audit
```

## Env

```
PLANTATION_SERVICE_URL=
PLANTATION_SERVICE_TOKEN=
PLANTATION_HMAC_SECRET=
```

Endpoint: `POST /api/internal/plantation/events` (Bearer + optional HMAC).
