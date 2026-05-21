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

php bin/console doctrine:migrations:migrate --no-interaction --env=prod
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod
php bin/console assets:install public --env=prod
```

### 5. JWT keys (mobile API)

Upload or generate on the server (not in git):

```bash
mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
```

Set `JWT_PASSPHRASE` in Forge to match.

### 6. Web directory

Forge **Web Directory** must be: `public`

### 7. If install still stops while “Downloading…”

- Retry deploy (Packagist/GitHub timeout)
- Check disk space: `df -h`
- SSH: `cd /home/forge/your-site && composer install -vvv --no-dev --no-scripts` to see the real error
