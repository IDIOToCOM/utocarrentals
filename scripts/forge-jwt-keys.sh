#!/usr/bin/env bash
# Ensure JWT keys exist and match JWT_PASSPHRASE (required for /api/login and mobile app).
set -euo pipefail

ROOT="${FORGE_SITE_PATH:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
cd "$ROOT"

export APP_ENV="${APP_ENV:-prod}"

# Forge "Commands" often do not inject Site Environment into bash — read .env if present.
load_env_from_dotenv() {
  local file key val
  for file in .env .env.local; do
    [ -f "$file" ] || continue
    while IFS= read -r line || [ -n "$line" ]; do
      line="${line%%#*}"
      line="$(echo "$line" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
      [ -n "$line" ] || continue
      key="${line%%=*}"
      val="${line#*=}"
      val="${val%\"}"
      val="${val#\"}"
      val="${val%\'}"
      val="${val#\'}"
      case "$key" in
        JWT_PASSPHRASE) [ -z "${JWT_PASSPHRASE:-}" ] && export JWT_PASSPHRASE="$val" ;;
      esac
    done < "$file"
  done
}
load_env_from_dotenv

mkdir -p config/jwt

verify_jwt() {
  php bin/console lexik:jwt:check-config --no-interaction --env=prod >/dev/null 2>&1
}

if [ -f config/jwt/private.pem ] && [ -f config/jwt/public.pem ] && verify_jwt; then
  echo "[forge-jwt] JWT keys OK (config/jwt/*.pem + JWT_PASSPHRASE)."
  exit 0
fi

if [ -f config/jwt/private.pem ]; then
  echo "[forge-jwt] Keys exist but JWT_PASSPHRASE mismatch — regenerating..."
  rm -f config/jwt/private.pem config/jwt/public.pem
fi

if [ -z "${JWT_PASSPHRASE:-}" ]; then
  export JWT_PASSPHRASE="$(php -r 'echo bin2hex(random_bytes(16));')"
  echo "[forge-jwt] JWT_PASSPHRASE was empty. Generated: $JWT_PASSPHRASE"
  echo "[forge-jwt] Add this exact value to Forge → Site → Environment → JWT_PASSPHRASE"
fi

if php bin/console lexik:jwt:generate-keypair --no-interaction --env=prod 2>/dev/null; then
  echo "[forge-jwt] Created keys via lexik:jwt:generate-keypair"
else
  echo "[forge-jwt] Using openssl fallback..."
  openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096 -pass "pass:${JWT_PASSPHRASE}"
  openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout -passin "pass:${JWT_PASSPHRASE}"
fi

if verify_jwt; then
  echo "[forge-jwt] JWT ready for mobile login (/api/login)."
else
  echo "[forge-jwt] ERROR: Keys created but check failed. Set JWT_PASSPHRASE in Forge Environment and re-run."
  exit 1
fi
