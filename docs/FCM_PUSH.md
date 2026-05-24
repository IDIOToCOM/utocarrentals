# Firebase push notifications (SAMSON)

SAMSON registers FCM device tokens via:

- `POST /api/mobile/v1/device-tokens` (JWT)
- `DELETE /api/mobile/v1/device-tokens` (JWT)

When an `AppNotification` is created (booking submitted, confirmed, cancelled, pickup reminder, etc.), the backend sends FCM HTTP v1 push to all tokens for that user.

## One-time setup (Firebase Console)

1. Open project **appdev-2ea68** → Project settings → **Service accounts**
2. **Generate new private key** → save JSON securely (never commit to git)
3. In Google Cloud Console, enable **Firebase Cloud Messaging API**

## Forge Environment

```env
FCM_PROJECT_ID=appdev-2ea68
FCM_SERVICE_ACCOUNT_JSON=/home/forge/uto.on-forge.com/storage/firebase-service-account.json
```

Upload the JSON to that path (or a Forge shared `storage/` path). Restrict permissions:

```bash
chmod 600 storage/firebase-service-account.json
```

Add `storage/firebase-service-account.json` to Forge **Shared paths** if you use persistent storage.

## Deploy

```bash
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
php bin/console cache:clear --env=prod
```

## Test

1. Rebuild SAMSON (`npm run android`), sign in once (registers token)
2. Confirm `POST /api/mobile/v1/device-tokens` returns `{ "registered": true }`
3. Trigger a booking notification (submit booking or confirm in admin)
4. Device should show push (background) or in-app alert (foreground)

If `FCM_PROJECT_ID` or the JSON path is empty, push is skipped — in-app notifications still work.

## Dependencies

- `google/auth` (Composer) for FCM OAuth2 access tokens
