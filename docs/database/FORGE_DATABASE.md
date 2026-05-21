# MySQL database for Laravel Forge

This document describes how to provision and migrate the **Uto Car Rentals** database on Forge.

## Quick setup on Forge (Laravel-style auto migrate)

1. **Site → Environment** — set `DATABASE_URL` with the database name you want (e.g. `uto_carrentals`):

```env
DATABASE_URL="mysql://DB_USER:DB_PASSWORD@127.0.0.1:3306/uto_carrentals?serverVersion=8.0&charset=utf8mb4"
```

Replace `DB_USER`, `DB_PASSWORD`, and database name. URL-encode special characters in the password (`@` → `%40`, etc.). Use Forge’s MySQL user from **Server → Database** (or the site-linked database).

2. **Deploy** — the deployment script (see [FORGE_DEPLOY.md](../FORGE_DEPLOY.md)) runs on every release:

```bash
php bin/console doctrine:database:create --if-not-exists --no-interaction --env=prod
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

| Step | Laravel equivalent | Symfony command |
|------|-------------------|-----------------|
| Create DB if missing | (often pre-created in Forge) | `doctrine:database:create --if-not-exists` |
| Create/update tables | `php artisan migrate --force` | `doctrine:migrations:migrate` |

You do **not** need to import `forge-mysql-schema.sql` for normal deploys. Migrations fill `doctrine_migration_versions` automatically.

**Optional:** create an empty database in Forge UI with the same name as in `DATABASE_URL` — deploy still works; `create --if-not-exists` is a no-op when it already exists.

**Requirement:** the MySQL user in `DATABASE_URL` must be allowed to connect to `127.0.0.1` and, on first deploy, to `CREATE DATABASE` if that database name does not exist yet.

## Files in this folder

| File | Purpose |
|------|---------|
| [forge-mysql-schema.sql](forge-mysql-schema.sql) | Full `CREATE TABLE` script (reference / manual import) |
| [forge-migration-versions-seed.sql](forge-migration-versions-seed.sql) | Optional: mark migrations as executed if you imported SQL by hand |

## Entity relationship (overview)

```mermaid
erDiagram
    login ||--o{ car_inventory : creates
    login ||--o{ booking : creates
    login ||--o{ payment : creates
    login ||--o{ car_favorite : has
    login ||--o{ car_review : writes
    login ||--o{ app_notification : receives

    user ||--o{ booking : customer_profile

    car_inventory ||--o{ booking : booked
    car_inventory ||--o{ payment : optional
    car_inventory ||--o{ car_favorite : favorited
    car_inventory ||--o{ car_review : reviewed

    booking ||--o| payment : paid_via
    booking ||--o{ app_notification : triggers
```

## Tables (12 application + 2 system)

### Authentication & users

| Table | Description |
|-------|-------------|
| `login` | Symfony security accounts (web + mobile JWT). Roles in JSON (`ROLE_ADMIN`, `ROLE_STAFF`, `ROLE_USER`). |
| `user` | Customer profile linked to bookings (name, email, phone, address). |

### Fleet & bookings

| Table | Description |
|-------|-------------|
| `car_inventory` | Vehicles: brand, model, type, `price_per_day`, `status` (`Available`, `Maintenance`, …), description, transmission, seats. |
| `booking` | Reservations: pickup/dropoff, dates/times, `status` (`Pending`, `Confirmed`, `Cancelled`, `Refunded`). |
| `payment` | Payment records tied to bookings (`amount_due`, `amount_paid`, `paid_at`). |

### Customer features

| Table | Description |
|-------|-------------|
| `car_favorite` | Saved cars per `login` (unique per user+car). |
| `car_review` | Star rating + comment per user+car (unique). |
| `app_notification` | In-app notifications for bookings. |

### System

| Table | Description |
|-------|-------------|
| `activity_log` | Admin audit trail (user, action, entity). |
| `messenger_messages` | Symfony Messenger queue (when `MESSENGER_TRANSPORT_DSN=doctrine://...`). |
| `doctrine_migration_versions` | Doctrine migration history. |

## Column reference (main fields)

### `login`

| Column | Type | Notes |
|--------|------|-------|
| `username` | VARCHAR(180) | Unique; used to sign in |
| `password` | VARCHAR(255) | bcrypt/argon hash |
| `roles` | JSON | e.g. `["ROLE_ADMIN"]` |
| `email` | VARCHAR(255) | Unique, nullable |
| `is_verified` | TINYINT | Email verification flag |
| `is_enabled` | TINYINT | Account active |

### `car_inventory`

| Column | Type | Notes |
|--------|------|-------|
| `price_per_day` | INT | Whole currency units (e.g. PHP pesos) |
| `status` | VARCHAR(255) | Fleet visibility / availability |
| `passenger_seats` | INT | Nullable |

### `booking`

| Column | Type | Notes |
|--------|------|-------|
| `status` | VARCHAR(20) | Default `Pending` |
| `pickup_time` / `return_time` | TIME | 30-minute intervals in app validation |

### `payment`

| Column | Type | Notes |
|--------|------|-------|
| `amount_due` | INT | Default 0 |
| `booking_id` | INT | FK → `booking`, nullable |

## Manual import (alternative to migrations)

Only use if you cannot run `doctrine:migrations:migrate`:

```bash
mysql -u forge -p uto_carrentals < docs/database/forge-mysql-schema.sql
```

Then either:

- Run migrations anyway (they should no-op if schema matches), or
- Import [forge-migration-versions-seed.sql](forge-migration-versions-seed.sql) so Doctrine does not try to re-apply old migrations.

**Warning:** Manual SQL does not create an admin user. Create one via the app register page or (on a secure SSH session only):

```bash
php bin/console doctrine:fixtures:load --no-interaction --env=prod
# Only if UserFixtures are acceptable for production — prefer creating admin via UI
```

## Create first admin (production-safe)

```bash
php bin/console app:create-admin  # if you add such a command
```

Or register through the site, then promote in MySQL:

```sql
UPDATE login SET roles = '["ROLE_ADMIN"]' WHERE username = 'your@email.com';
```

(Use the same JSON format Symfony expects.)

## Forge `DATABASE_URL` examples

**Site database (localhost):**

```env
DATABASE_URL="mysql://forge:SECRET@127.0.0.1:3306/uto_carrentals?serverVersion=8.0.32&charset=utf8mb4"
```

**Password with special characters** — encode in the URL:

| Character | Encoded |
|-----------|---------|
| `@` | `%40` |
| `#` | `%23` |
| `%` | `%25` |

## Troubleshooting

| Error | Fix |
|-------|-----|
| `Access denied for user` | Wrong user/password in `DATABASE_URL` |
| `Unknown database` | Create DB in Forge → Database |
| `Table doesn't exist` | Run `doctrine:migrations:migrate --env=prod` |
| `Migration already executed` | Schema imported manually + seed migration versions file |
| Connection from app works but migrate fails | Run migrate as `forge` user in project `current/` directory |

## Keeping schema in sync

After changing entities locally, always:

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
git add migrations/
git push
```

On Forge deploy, the deployment script should include:

```bash
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

See also: [../FORGE_DEPLOY.md](../FORGE_DEPLOY.md).
