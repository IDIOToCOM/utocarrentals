#!/usr/bin/env bash
set -euo pipefail

cd "${FORGE_SITE_PATH:-$(dirname "$0")/..}"

composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev --no-scripts

if command -v npm >/dev/null 2>&1 && [ -f package.json ]; then
  npm ci
  npm run build
fi

# Laravel-style: create DB if missing, then apply migrations (tables + schema updates)
php bin/console doctrine:database:create --if-not-exists --no-interaction --env=prod
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
php bin/console cache:clear --env=prod --no-warmup --no-interaction
php bin/console cache:warmup --env=prod --no-interaction
php bin/console assets:install public --env=prod --no-interaction
