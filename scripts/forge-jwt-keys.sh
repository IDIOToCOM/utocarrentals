#!/usr/bin/env bash
# Generate JWT keys on the server if missing (required for Lexik JWT bundle boot).
set -euo pipefail

ROOT="${FORGE_SITE_PATH:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
cd "$ROOT"

if [ -f config/jwt/private.pem ] && [ -f config/jwt/public.pem ]; then
  echo "[forge-jwt] JWT keys already present."
  exit 0
fi

mkdir -p config/jwt

PASS="${JWT_PASSPHRASE:-}"
if [ -z "$PASS" ]; then
  PASS="$(php -r 'echo bin2hex(random_bytes(16));')"
  echo "[forge-jwt] WARNING: JWT_PASSPHRASE was empty. Generated keys with a random passphrase."
  echo "[forge-jwt] Add to Forge Environment: JWT_PASSPHRASE=$PASS"
fi

openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096 -pass "pass:${PASS}"
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout -passin "pass:${PASS}"

echo "[forge-jwt] Created config/jwt/private.pem and public.pem"
