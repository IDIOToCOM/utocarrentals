#!/usr/bin/env bash
# Persist car photo uploads across Forge deploys (zero-downtime safe).
set -euo pipefail

cd "${FORGE_SITE_PATH:-$(dirname "$0")/..}"

STORAGE_REL="${CAR_PHOTOS_STORAGE_DIR:-storage/car_photos}"
if [[ "${STORAGE_REL}" == /* ]]; then
  STORAGE_DIR="${STORAGE_REL}"
else
  STORAGE_DIR="$(pwd)/${STORAGE_REL}"
fi

PUBLIC_UPLOAD_LINK="$(pwd)/public/uploads/cars"
LEGACY_DIR="$(pwd)/public/images/cars"

mkdir -p "${STORAGE_DIR}" "$(dirname "${PUBLIC_UPLOAD_LINK}")"
chmod -R ug+rwx "$(dirname "${STORAGE_DIR}")" 2>/dev/null || true

# Serve storage at /uploads/cars/ (symlink survives each release when storage/ is a Forge shared path)
rm -f "${PUBLIC_UPLOAD_LINK}"
ln -sfn "${STORAGE_DIR}" "${PUBLIC_UPLOAD_LINK}"

# Move uploads from old location (public/images/cars/*.jpg) into persistent storage
shopt -s nullglob
for ext in jpg jpeg png webp; do
  for f in "${LEGACY_DIR}"/*."${ext}"; do
    base=$(basename "${f}")
    if [[ ! -f "${STORAGE_DIR}/${base}" ]]; then
      mv "${f}" "${STORAGE_DIR}/${base}"
    else
      rm -f "${f}"
    fi
  done
done
shopt -u nullglob

echo "Car photos: ${STORAGE_DIR} -> public/uploads/cars"
