# Google Sign-In setup (Web + React Native)

## 1. Google Cloud Console

1. [Google Cloud Console](https://console.cloud.google.com/) → **APIs & Services** → **Credentials**
2. Create **OAuth 2.0 Client ID** → type **Web application**
3. Copy **Client ID** and **Client secret** (`GOCSPX-...`)

### Authorized JavaScript origins

```
https://utocarrentals-hxyws5o9.on-forge.com
http://localhost:8000
```

(Add your custom domain if you use one.)

### Authorized redirect URIs (required for website)

```
https://utocarrentals-hxyws5o9.on-forge.com/connect/google/check
http://localhost:8000/connect/google/check
```

`DEFAULT_URI` in Forge Environment must match the **https** site URL.

## 2. Symfony / Forge Environment

```env
GOOGLE_CLIENT_ID=798060321628-xxxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxxxx

DEFAULT_URI=https://utocarrentals-hxyws5o9.on-forge.com
```

When both are set, **Continue with Google** appears on `/login` and `/register`.

## 3. React Native (SAMSON)

`src/config/google.ts` — use the **same Web client ID** as `GOOGLE_CLIENT_ID`:

```typescript
export const GOOGLE_WEB_CLIENT_ID = '798060321628-xxxxx.apps.googleusercontent.com';
```

For **Android**, in the same Google Cloud project:

1. Create **Android** OAuth client (package name + SHA-1 from debug/release keystore)
2. Keep using **Web client ID** in `GoogleSignin.configure({ webClientId })` (required for `idToken`)

Get debug SHA-1:

```bash
cd android && ./gradlew signingReport
```

Add that SHA-1 to the Android OAuth client in Google Cloud.

## 4. Mobile API

`POST /api/auth/google` with body:

```json
{ "idToken": "<from Google Sign-In>" }
```

Response: `{ "token": "...", "username": "...", "email": "..." }`

Requires **JWT keys** on the server (`scripts/forge-jwt-keys.sh`).

## 5. Troubleshooting

| Problem | Fix |
|---------|-----|
| Button missing on web | Set `GOOGLE_CLIENT_ID` + `GOOGLE_CLIENT_SECRET` in Forge Environment |
| `redirect_uri_mismatch` | Add exact `/connect/google/check` URL in Google Console |
| Mobile `audience mismatch` | `GOOGLE_WEB_CLIENT_ID` must equal Symfony `GOOGLE_CLIENT_ID` |
| 500 on Google login | Fix `JWT_PASSPHRASE` + `config/jwt/*.pem` (see REACT_NATIVE_FORGE.md) |
| HTTP redirect on Forge | Deploy includes `trusted_proxies` in `framework.yaml` (prod) |
