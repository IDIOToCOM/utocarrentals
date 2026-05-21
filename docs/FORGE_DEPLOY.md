# Symfony deploy on Laravel Forge (auto MySQL)

## One-time Forge setup (required before deploy works)

### 1. Install MySQL on the server

**Forge → Server** → **Database** → **Install MySQL**

Wait until MySQL is running. Without this you get `Connection refused`.

### 2. Install database on the site

**Forge → Site** → **Database** → **Install Database**

Forge creates `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` automatically.

### 3. Site → Environment

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

---

## Deployment script (paste in Forge → Deployments)

```bash
cd $FORGE_SITE_PATH

git pull origin $FORGE_SITE_BRANCH

composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev --no-scripts

npm ci
npm run build

# Auto create MySQL database + tables (uses scripts/forge-database.sh)
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

## JWT (mobile API, one-time SSH)

```bash
mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
```

Set `JWT_PASSPHRASE` in Forge Environment.

---

## Push to GitHub

Commit and push `scripts/forge-database.sh` and `scripts/forge-deploy.sh`, then deploy on Forge.
