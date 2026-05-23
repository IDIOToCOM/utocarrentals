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

LEGACY_DIR="$(pwd)/public/images/cars"
OLD_UPLOAD_DIR="$(pwd)/public/uploads/cars"

mkdir -p "${STORAGE_DIR}"
chmod -R ug+rwx "$(dirname "${STORAGE_DIR}")" 2>/dev/null || true

# Remove broken symlink from older deploys (photos are served via /media/cars/ now)
if [[ -L "${OLD_UPLOAD_DIR}" ]]; then
  rm -f "${OLD_UPLOAD_DIR}"
fi

shopt -s nullglob
for ext in jpg jpeg png webp; do
  for source_dir in "${LEGACY_DIR}" "${OLD_UPLOAD_DIR}"; do
    [[ -d "${source_dir}" ]] || continue
    for f in "${source_dir}"/*."${ext}"; do
      base=$(basename "${f}")
      if [[ ! -f "${STORAGE_DIR}/${base}" ]]; then
        mv "${f}" "${STORAGE_DIR}/${base}"
      else
        rm -f "${f}"
      fi
    done
  done
done
shopt -u nullglob

echo "Car photos storage: ${STORAGE_DIR} (served at /media/cars/{id}.ext)"
