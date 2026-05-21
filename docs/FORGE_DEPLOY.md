# Deploy on Laravel Forge (Symfony)

## Fix: `DebugBundle` not found during deploy

If deploy fails with:

`Class "Symfony\Bundle\DebugBundle\DebugBundle" not found`

Composer installed with `--no-dev` (no debug bundle), but `cache:clear` ran in **dev** mode. The repo now:

- Registers dev bundles only when their classes exist (`config/bundles.php`)
- Runs post-install `cache:clear` with `--env=prod`

Set **`APP_ENV=prod`** in Forge Environment anyway.

## Fix: “Forge was unable to install Composer dependencies”

Forge runs `composer install` **before** your deploy script. This project’s `post-install-cmd` runs `cache:clear`, which needs `APP_SECRET` and can fail on a fresh server.

### 1. Forge → Site → Environment (set **before** deploy)

```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<run: php -r "echo bin2hex(random_bytes(16));">

COMPOSER_MEMORY_LIMIT=-1
```

Add `DATABASE_URL`, `JWT_PASSPHRASE`, `MAILER_DSN`, `DEFAULT_URI`, etc. (see `.env.example`).

**MySQL setup:** see [database/FORGE_DATABASE.md](database/FORGE_DATABASE.md) and [database/forge-mysql-schema.sql](database/forge-mysql-schema.sql) for full table structure, ER diagram, and Forge `DATABASE_URL` examples.

### 2. PHP version & extensions

- **PHP 8.2 or 8.3** (not 8.0 / 8.1)
- Enable: `ctype`, `iconv`, `mbstring`, `json`, `xml`, `openssl`, `sodium`, `pdo_mysql`, `intl`, `fileinfo`, `curl`

### 3. Composer install options (critical)

In Forge, open the site’s deployment / composer settings and use:

```bash
install --no-interaction --prefer-dist --optimize-autoloader --no-dev --no-scripts
```

`--no-scripts` skips `cache:clear` during Composer; you warm cache in the deploy script instead.

If Forge has no custom Composer field, use a **Deployment Script** only and disable automatic Composer install (if your plan allows), then run:

```bash
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev --no-scripts
```

### 4. Deployment script

```bash
cd $FORGE_SITE_PATH

git pull origin $FORGE_SITE_BRANCH

composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev --no-scripts

npm ci
npm run build

# Create MySQL database + tables on every deploy (like `php artisan migrate --force`)
php bin/console doctrine:database:create --if-not-exists --no-interaction --env=prod
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod
php bin/console assets:install public --env=prod
```

### 5. Database (MySQL 8)

1. In Forge: **Server → Database** → create database (e.g. `uto_carrentals`).
2. Link it to the site and copy credentials into `DATABASE_URL`.
3. On deploy/SSH:

```bash
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

Full schema reference: [docs/database/FORGE_DATABASE.md](database/FORGE_DATABASE.md).

### 6. JWT keys (mobile API)

Upload or generate on the server (not in git):

```bash
mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
```

Set `JWT_PASSPHRASE` in Forge to match.

### 7. Web directory

Forge **Web Directory** must be: `public`

### 8. If install still stops while “Downloading…”

- Retry deploy (Packagist/GitHub timeout)
- Check disk space: `df -h`
- SSH: `cd /home/forge/your-site && composer install -vvv --no-dev --no-scripts` to see the real error

## Fix: deploy OK but site shows **500 Internal Server Error**

Deployment can succeed while the live site still fails. Your deploy log only ran **Composer** — it did **not** run `npm run build`, migrations, or JWT setup.

### Step 1 — Read the real error (SSH)

```bash
cd /home/forge/utocarrentals-kwpbnllq.on-forge.com/current
# or: cd $FORGE_SITE_PATH

tail -100 var/log/prod.log
# Forge also shows PHP/nginx errors under the site → Logs tab
```

### Step 2 — Most common causes (check in order)

| Cause | Symptom in logs | Fix |
|-------|-----------------|-----|
| **No Webpack build** | `entrypoints.json` / Encore exception | `npm ci && npm run build` (creates `public/build/`) |
| **Missing JWT keys** | `private.pem` / JWT / openssl error | Generate `config/jwt/*.pem` (see §5 above) |
| **Bad `DATABASE_URL`** | SQL connection refused / access denied | Create MySQL DB in Forge, set URL, run migrations |
| **Missing env vars** | `Environment variable not found` | Copy all keys from `.env.example` into Forge Environment |
| **`var/` not writable** | Permission denied | `chmod -R ug+rwx var` (Forge usually handles this) |

### Step 3 — Full Forge Environment (minimum)

```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<random-32-chars>

DEFAULT_URI=https://utocarrentals-kwpbnllq.on-forge.com

DATABASE_URL="mysql://USER:PASS@127.0.0.1:3306/DATABASE?serverVersion=8.0&charset=utf8mb4"

MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0

MAILER_DSN=null://null
MAILER_DEFAULT_FROM_EMAIL=noreply@yourdomain.com
MAILER_DEFAULT_FROM_NAME="UTO Mobility"

JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=<same-as-when-you-generated-keys>

CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1|utocarrentals-kwpbnllq\.on-forge\.com)(:[0-9]+)?$'

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=

COMPOSER_MEMORY_LIMIT=-1
```

### Step 4 — One-time setup on the server (SSH)

```bash
cd /home/forge/utocarrentals-kwpbnllq.on-forge.com/current

# Frontend assets (required — templates call encore_entry_*)
npm ci
npm run build

# Database (create + migrate)
php bin/console doctrine:database:create --if-not-exists --no-interaction --env=prod
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

# JWT (if not done yet)
mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
# Enter passphrase; set JWT_PASSPHRASE in Forge to match

php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

### Step 5 — Update Forge deployment script

Use the script in **§4** so every deploy runs `npm run build` and migrations — not only `composer install`.
