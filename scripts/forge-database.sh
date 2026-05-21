#!/usr/bin/env bash
# Forge deploy: create MySQL database + run migrations (Laravel migrate --force equivalent).
set -euo pipefail

ROOT="${FORGE_SITE_PATH:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
cd "$ROOT"

export APP_ENV="${APP_ENV:-prod}"

urlencode() {
  FORGE_ENCODE_VALUE="$1" php -r 'echo rawurlencode((string) getenv("FORGE_ENCODE_VALUE"));'
}

ensure_database_url() {
  if [ -n "${DATABASE_URL:-}" ]; then
    echo "[forge-database] Using DATABASE_URL from Forge environment."
    return 0
  fi

  local user="${DB_USERNAME:-${MYSQL_USER:-}}"
  local pass="${DB_PASSWORD:-${MYSQL_PASSWORD:-}}"
  local host="${DB_HOST:-127.0.0.1}"
  local port="${DB_PORT:-3306}"
  local db="${DB_DATABASE:-${MYSQL_DATABASE:-}}"

  if [ -z "$user" ] || [ -z "$db" ]; then
    echo ""
    echo "ERROR: MySQL is not configured for deploy."
    echo "  1. Forge → Server → Database → Install MySQL (must be running)"
    echo "  2. Forge → Site → Database → Install Database"
    echo "  3. Forge → Site → Environment → set DATABASE_URL, for example:"
    echo '     DATABASE_URL="mysql://forge:PASSWORD@127.0.0.1:3306/your_db?serverVersion=8.0&charset=utf8mb4"'
    echo ""
    echo "  Or rely on Forge DB_* variables (DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE)."
    echo ""
    exit 1
  fi

  local enc_user enc_pass
  enc_user="$(urlencode "$user")"
  enc_pass="$(urlencode "$pass")"
  export DATABASE_URL="mysql://${enc_user}:${enc_pass}@${host}:${port}/${db}?serverVersion=8.0&charset=utf8mb4"
  echo "[forge-database] Built DATABASE_URL from Forge DB_* (host=${host}, db=${db})."
}

run_doctrine_db() {
  php bin/console doctrine:database:create --if-not-exists --no-interaction --env=prod
  php bin/console doctrine:migrations:migrate --no-interaction --env=prod
}

print_mysql_help() {
  echo ""
  echo "ERROR: Cannot connect to MySQL (connection refused or wrong host/port)."
  echo "  - Forge → Server → Database → Install MySQL, then: sudo systemctl start mysql"
  echo "  - Use port 3306 in DATABASE_URL (not local Docker port 3307)"
  echo "  - Site → Database → Install Database, then copy credentials to Environment"
  echo ""
}

ensure_database_url

err_file="$(mktemp)"
trap 'rm -f "$err_file"' EXIT

if ! run_doctrine_db 2>"$err_file"; then
  if grep -q "Connection refused" "$err_file" && [[ "${DATABASE_URL}" == *"127.0.0.1"* ]]; then
    echo "[forge-database] Retrying with localhost (socket) instead of 127.0.0.1..."
    export DATABASE_URL="${DATABASE_URL//127.0.0.1/localhost}"
    if ! run_doctrine_db 2>"$err_file"; then
      cat "$err_file" >&2
      print_mysql_help
      exit 1
    fi
  else
    cat "$err_file" >&2
    if grep -q "Connection refused" "$err_file"; then
      print_mysql_help
    fi
    exit 1
  fi
fi

echo "[forge-database] Database and tables are ready."
