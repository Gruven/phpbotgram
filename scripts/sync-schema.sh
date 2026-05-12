#!/usr/bin/env bash
# Re-vendor the .butcher schema from a checkout of aiogram.
# Usage: scripts/sync-schema.sh /path/to/aiogram/.butcher
set -euo pipefail

UPSTREAM_PATH="${1:?usage: sync-schema.sh /path/to/aiogram/.butcher}"

if [[ ! -d "${UPSTREAM_PATH}/schema" ]]; then
  echo "error: ${UPSTREAM_PATH}/schema does not exist" >&2
  exit 1
fi

rsync -a --delete "${UPSTREAM_PATH}/schema/"  .butcher/schema/
rsync -a --delete "${UPSTREAM_PATH}/types/"   .butcher/types/
rsync -a --delete "${UPSTREAM_PATH}/methods/" .butcher/methods/
rsync -a --delete "${UPSTREAM_PATH}/enums/"   .butcher/enums/

echo "Schema synced from ${UPSTREAM_PATH}"
echo "  types:   $(ls .butcher/types   | wc -l | tr -d ' ')"
echo "  methods: $(ls .butcher/methods | wc -l | tr -d ' ')"
echo "  enums:   $(ls .butcher/enums   | wc -l | tr -d ' ')"
