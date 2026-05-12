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
# `entity.json` files are vendored by upstream butcher but never consumed by
# SchemaLoader (it reads `.butcher/schema/schema.json` plus per-entity
# `replace.yml`/`aliases.yml`/`default.yml`/`subtypes.yml` patch files only).
# Excluding them keeps ~50MB of dead weight out of the tree.
rsync -a --delete --exclude='entity.json' "${UPSTREAM_PATH}/types/"   .butcher/types/
rsync -a --delete --exclude='entity.json' "${UPSTREAM_PATH}/methods/" .butcher/methods/
rsync -a --delete "${UPSTREAM_PATH}/enums/"   .butcher/enums/

echo "Schema synced from ${UPSTREAM_PATH}"
echo "  types:   $(ls .butcher/types   | wc -l | tr -d ' ')"
echo "  methods: $(ls .butcher/methods | wc -l | tr -d ' ')"
echo "  enums:   $(ls .butcher/enums   | wc -l | tr -d ' ')"
