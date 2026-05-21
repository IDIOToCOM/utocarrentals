# Connect React Native (SAMSON) to Forge production

Symfony API base: `https://utocarrentals-hxyws5o9.on-forge.com`  
Mobile prefix: `/api/mobile/v1`  
JWT login: `POST /api/login`

---

## 1. Forge backend checklist

| Step | Action |
|------|--------|
| DB + tables | Migrations ran (`login` table exists) |
| Users | `php bin/console app:create-user ...` or register on website |
| JWT keys | **Required** — see [Fix mobile “JWT keys on Forge”](#fix-mobile-jwt-keys-on-forge) |
| CORS | `CORS_ALLOW_ORIGIN` includes your Forge domain (see below) |

### Forge Environment (mobile-related)

```env
DEFAULT_URI=https://utocarrentals-hxyws5o9.on-forge.com

CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1|utocarrentals-hxyws5o9\.on-forge\.com)(:[0-9]+)?$'

JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your_jwt_passphrase

GOOGLE_CLIENT_ID=your_web_client_id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-...
```

Native apps usually **do not** need CORS changes; CORS matters for browser/WebView only.

---

## Fix mobile “JWT keys on Forge”

Mobile login calls `POST /api/login` → Symfony needs `config/jwt/private.pem`, `public.pem`, and matching **`JWT_PASSPHRASE`** in Forge Environment.

### Step 1 — Forge Environment

Must include (same passphrase everywhere):

```env
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your_passphrase_here
```

Must **exactly match** the passphrase used when `config/jwt/*.pem` were created.

If `forge-jwt-keys.sh` printed `JWT_PASSPHRASE=...` in the command output, copy **that** value into Forge Environment (Forge Commands may not pass env vars into bash).

### Step 2 — Forge → Commands (run once)

```bash
bash scripts/forge-jwt-keys.sh
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

You should see: `[forge-jwt] JWT ready for mobile login`.

### Step 3 — Zero-downtime deploys

**Site → Advanced → Shared paths** add:

```text
config/jwt
```

Otherwise each deploy wipes JWT files (they are not in git).

### Step 4 — Test login

```bash
curl -s -X POST https://utocarrentals-hxyws5o9.on-forge.com/api/login \
  -H "Content-Type: application/json" \
  -d '{"username":"juan","password":"YOUR_PASSWORD"}'
```

Expect `"token":"eyJ..."` not HTML or 500.

### Step 5 — Retry mobile app

Reload app → sign in with username/password (same user as website).

---

## 2. Test API from Forge Commands

```bash
php bin/console dbal:run-sql "SELECT username FROM login LIMIT 5" --env=prod
```

```bash
curl -s -X POST https://utocarrentals-hxyws5o9.on-forge.com/api/login \
  -H "Content-Type: application/json" \
  -d '{"username":"staff","password":"staff123"}'
```

Expect JSON with `"token": "eyJ..."`.

```bash
curl -s https://utocarrentals-hxyws5o9.on-forge.com/api/mobile/v1/health
```

Expect `"success": true`.

---

## 3. React Native app (SAMSON)

Edit `src/config/api.ts`:

```typescript
const FORGE_API = 'https://utocarrentals-hxyws5o9.on-forge.com';
const DEV_API = 'http://10.0.2.2:8000'; // Android emulator → PC
// const DEV_API = 'http://192.168.1.XXX:8000'; // physical phone → PC LAN IP

export const API_BASE_URL = __DEV__ ? DEV_API : FORGE_API;
export const MOBILE_API = `${API_BASE_URL}/api/mobile/v1`;
```

| Run target | `API_BASE_URL` |
|------------|----------------|
| Release / production build | Forge HTTPS URL |
| Emulator + local Symfony | `http://10.0.2.2:8000` |
| Physical phone + local Symfony | Your PC LAN IP `:8000` |

Rebuild the app after changing `api.ts`.

---

## 4. Login flow (app)

| Endpoint | Auth |
|----------|------|
| `POST /api/login` | Body `{ "username", "password" }` → `{ "token" }` |
| `POST /api/register` | Create account |
| `POST /api/auth/google` | Body `{ "idToken" }` (Google Sign-In) |
| `GET /api/mobile/v1/cars` | Public |
| `GET /api/mobile/v1/bookings` | Header `Authorization: Bearer <token>` |

Same users as the website (`login` table). Create users on Forge or via `app:create-user`.

---

## 5. Google Sign-In on mobile

1. Use the **Web application** OAuth client ID in `src/config/google.ts` (same as `GOOGLE_CLIENT_ID` on Forge).
2. In Google Cloud Console, add your Android/iOS OAuth clients for the native app.
3. Set `SHOW_GOOGLE_SIGN_IN_UI = true` in `google.ts` when ready.
4. Forge must have matching `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET`.

---

## 6. Android cleartext (local dev only)

For `http://10.0.2.2` or LAN HTTP, Android may require cleartext in network security config. **Production Forge uses HTTPS** — no extra config.

---

## 7. Staff vs customer on mobile

Mobile booking API requires **ROLE_USER** (customer). Staff accounts (`ROLE_STAFF`) use the **web** admin UI, not the customer mobile flows.

Create a **customer** for the app:

```bash
php bin/console app:create-user juan juan@example.com Pass123! --role=ROLE_USER --env=prod
```

---

## Full mobile API list

See SAMSON `docs/API.md` or Symfony `src/Controller/MobileApiController.php`.
