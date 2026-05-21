#!/usr/bin/env bash
# Full Forge deployment: dependencies, assets, MySQL, cache.
set -euo pipefail

cd "${FORGE_SITE_PATH:-$(dirname "$0")/..}"

mkdir -p var/cache var/log var/sessions config/jwt
chmod -R ug+rwx var 2>/dev/null || true

composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev --no-scripts

if command -v npm >/dev/null 2>&1 && [ -f package.json ]; then
  npm ci
  npm run build
fi

bash scripts/forge-jwt-keys.sh
bash scripts/forge-database.sh

php bin/console cache:clear --env=prod --no-warmup --no-interaction
php bin/console cache:warmup --env=prod --no-interaction
php bin/console assets:install public --env=prod --no-interaction
