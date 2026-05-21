# Uto Mobility — Symfony Backend

Car rental management application built with Symfony 7.3, Doctrine ORM, API Platform, and JWT authentication.

## Requirements

- PHP 8.2+
- Composer
- Node.js 18+ and npm (for Webpack Encore assets)
- MySQL 8.0 (or use Docker Compose below)

## Quick start

### 1. Clone and install dependencies

```bash
git clone https://github.com/IDIOToCOM/utocarrentals.git
cd utocarrentals
composer install
npm install
npm run build
```

### 2. Environment configuration

Copy the committed defaults and add your local secrets (this file is **not** committed):

```bash
cp .env .env.local
```

Edit `.env.local` and set at minimum:

| Variable | Description |
|----------|-------------|
| `APP_SECRET` | Random string (`php -r "echo bin2hex(random_bytes(16));"`) |
| `DATABASE_URL` | MySQL connection string |
| `MAILER_DSN` | SMTP DSN (e.g. Brevo) |
| `MAILER_DEFAULT_FROM_EMAIL` | Verified sender address |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | Google OAuth (staff login) |
| `JWT_PASSPHRASE` | Passphrase used when generating JWT keys |

Generate JWT keys (ignored by git):

```bash
mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
```

Set `JWT_PASSPHRASE` in `.env.local` to match the passphrase you chose.

### 3. Database

Start MySQL with Docker (optional):

```bash
docker compose up -d
```

Run migrations:

```bash
php bin/console doctrine:migrations:migrate
```

### 4. Run the application

```bash
symfony serve
# or: php -S localhost:8000 -t public
```

Open [http://localhost:8000](http://localhost:8000).

For asset hot-reload during development:

```bash
npm run watch
```

## Docker services

`docker-compose.yaml` provides:

- **MySQL** on port `3307`
- **phpMyAdmin** on port `8080`

Credentials are read from `.env` / `.env.local` (`MYSQL_*` variables).

## Security notes

- Never commit `.env.local`, JWT `.pem` files, or real API keys.
- Use `.env` for placeholders only; put secrets in `.env.local`.
