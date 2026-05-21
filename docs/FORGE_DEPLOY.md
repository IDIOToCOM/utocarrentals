# Symfony deploy on Laravel Forge (auto MySQL)

## One-time Forge setup (required before deploy works)

### 1. Install MySQL on the server

**Forge → Server** → **Database** → **Install MySQL**

Wait until MySQL is running. Without this you get `Connection refused`.

### 2. Install database on the site

**Forge → Site** → **Database** → **Install Database**

Forge creates `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` automatically.

### 3. Site → Environment

**Full template:** copy from [`docs/FORGE_ENV.example`](FORGE_ENV.example) (includes `APP_SECRET`, JWT, database, Google, mailer).

Minimum:

```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<random-32-chars>

DEFAULT_URI=https://your-site.on-forge.com
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
MAILER_DSN=null://null
```

**Option A — let deploy build the URL** (after step 2, Forge already has `DB_*` vars):

Do **not** set `DATABASE_URL`; `scripts/forge-database.sh` builds it from `DB_*` on each deploy.

**Option B — set `DATABASE_URL` explicitly** (recommended if you use a custom DB name):

```env
DATABASE_URL="mysql://forge:YOUR_PASSWORD@127.0.0.1:3306/uto_carrentals?serverVersion=8.0&charset=utf8mb4"
```

- Use Forge’s real user, password, and database name.
- Port **3306** (not `3307` from local Docker).
- URL-encode special characters in the password (`@` → `%40`).

### 4. Web directory

**Site → Meta** → Web Directory: `public`

### 5. Composer install command (Forge site settings)

```bash
install --no-interaction --prefer-dist --optimize-autoloader --no-dev --no-scripts
```

`--no-scripts` skips `cache:clear` during Composer until Environment is set.

**Fix:** `non-existent parameter "app.show_google_sign_in_ui"` — push latest `config/services.yaml` from GitHub, then retry install.

---

## Deployment script (paste in Forge → Deployments)

```bash
cd $FORGE_SITE_PATH

git pull origin $FORGE_SITE_BRANCH

composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev --no-scripts

npm ci
npm run build

# Auto create MySQL database + tables (uses scripts/forge-database.sh)
mkdir -p var/cache var/log var/sessions config/jwt
chmod -R ug+rwx var 2>/dev/null || true

bash scripts/forge-jwt-keys.sh
bash scripts/forge-database.sh

php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod
php bin/console assets:install public --env=prod
```

**Or:**

```bash
cd $FORGE_SITE_PATH
git pull origin $FORGE_SITE_BRANCH
bash scripts/forge-deploy.sh
```

---

## What runs on each deploy

| Step | Command |
|------|---------|
| Create DB if missing | `doctrine:database:create --if-not-exists` |
| Create/update tables | `doctrine:migrations:migrate` |

Implemented in [`scripts/forge-database.sh`](../scripts/forge-database.sh).

---

## Fix: `Connection refused` (SQLSTATE 2002)

| Cause | Fix |
|-------|-----|
| MySQL not installed | Server → Database → Install MySQL |
| MySQL stopped | SSH: `sudo systemctl start mysql` |
| Wrong `DATABASE_URL` | Use Forge DB credentials, port **3306** |
| Only local `.env` | Set vars in **Forge Environment**, not only in git `.env` |

SSH test:

```bash
cd $FORGE_SITE_PATH
sudo systemctl status mysql
bash scripts/forge-database.sh
```

---

## Shared paths (zero-downtime deploys)

If **Zero downtime deployments** is enabled, add **Shared paths**:

| Path | Why |
|------|-----|
| `config/jwt` | JWT keys survive new releases |
| `var/sessions` | Login/register sessions persist |

---

## Fix: login / register **500** (site home works)

The homepage does not use sessions; `/login` and `/register` do (CSRF + security).

### 1. Check the error (SSH)

```bash
cd $FORGE_SITE_PATH
tail -80 var/log/prod.log
```

### 2. Fix checklist

| Check | Forge / SSH |
|-------|-------------|
| `APP_SECRET` set | Site → Environment (32+ random chars) |
| `DEFAULT_URI` | `https://your-site.on-forge.com` |
| Migrations ran | `bash scripts/forge-database.sh` |
| JWT keys exist | `ls config/jwt/` → run `bash scripts/forge-jwt-keys.sh` |
| `JWT_PASSPHRASE` matches keys | Environment |
| `var/` writable | `chmod -R ug+rwx var` |
| `login` table exists | `php bin/console doctrine:query:sql "SHOW TABLES"` |

### 3. Minimum Environment for auth

```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<random-32-chars>
DEFAULT_URI=https://utocarrentals-hxyws5o9.on-forge.com
JWT_PASSPHRASE=<same-as-when-keys-generated>
```

Push latest code, redeploy, then try `/login` again.

---

## JWT (mobile API) — required for app login

If the mobile app shows **“JWT keys on Forge”**, run on the server:

```bash
bash scripts/forge-jwt-keys.sh
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

Set `JWT_PASSPHRASE` in Forge Environment to match. Add shared path **`config/jwt`** if using zero-downtime deploys.

See [REACT_NATIVE_FORGE.md](REACT_NATIVE_FORGE.md).

## JWT (manual OpenSSL)

```bash
mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
```

Set `JWT_PASSPHRASE` in Forge Environment.

---

## Create a user from Forge (SSH / Commands)

After migrations, create an admin (push `app:create-user` command first):

```bash
php bin/console app:create-user admin admin@example.com YourPassword123 --role=ROLE_ADMIN --env=prod
```

Customer account:

```bash
php bin/console app:create-user juan juan@example.com YourPassword123 --role=ROLE_USER --env=prod
```

---

## React Native app (SAMSON)

Point the mobile app at your Forge URL. See [REACT_NATIVE_FORGE.md](REACT_NATIVE_FORGE.md).

---

## Push to GitHub

Commit and push `scripts/forge-database.sh` and `scripts/forge-deploy.sh`, then deploy on Forge.
