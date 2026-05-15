#!/usr/bin/env bash
# scripts/build-docs.sh — Phase 10 docs build wrapper.
# Invoked by `composer docs-api` and `make docs-api`. Never call `vendor/bin/phpdoc`
# directly; this wrapper enforces the gate chain.
set -euo pipefail

: "${VERSION:?VERSION env var must be set (use 0.1.0-dev locally)}"

# Anchor cwd at the repo root so .phpdoc/template/ auto-discovery resolves
# relative to phpdoc.dist.xml (which lives at the repo root).
cd "$(dirname "$0")/.."

# build/docs is created AFTER the cd so it lands at repo/build/docs/.
mkdir -p build/docs build/docs/root-publish

# Resolve envsubst. macOS gettext is keg-only; fall back to common paths.
ENVSUBST_BIN="$(command -v envsubst 2>/dev/null || true)"
[ -z "$ENVSUBST_BIN" ] && [ -x /usr/local/opt/gettext/bin/envsubst ] && ENVSUBST_BIN=/usr/local/opt/gettext/bin/envsubst
[ -z "$ENVSUBST_BIN" ] && [ -x /opt/homebrew/opt/gettext/bin/envsubst ] && ENVSUBST_BIN=/opt/homebrew/opt/gettext/bin/envsubst
[ -z "$ENVSUBST_BIN" ] && { echo "envsubst not found (install with: brew install gettext)" >&2; exit 1; }

# Expand ${VERSION} only; the allow-list quote keeps $HOME/$PATH/etc. untouched.
"$ENVSUBST_BIN" '${VERSION}' < phpdoc.dist.xml.tpl > phpdoc.dist.xml

# Copy project-root CHANGELOG/CONTRIBUTING into the narrative tree.
php scripts/copy-root-docs.php

# Run phpdoc; capture stderr+stdout text-clean (no ANSI, no progress bar).
vendor/bin/phpdoc -c phpdoc.dist.xml --no-ansi --no-progress 2>&1 \
  | tee build/docs/build.out

# Doc-quality gates (each script exits non-zero on failure → pipefail kills us).
php scripts/check-docs-build-log.php build/docs/build.out

# Copy language-agnostic shared assets into the rendered output so guide
# pages can reference them via paths relative to <base href>.
mkdir -p build/docs/api/guide/shared
cp -r docs/guide/shared/. build/docs/api/guide/shared/

php scripts/check-docs-links.php
php scripts/rewrite-api-links.php
php scripts/check-internal-links.php
php scripts/lint-docs.php
php scripts/check-docs-examples.php

# Markdown style. Pinned version so a future cli2 breaking release doesn't
# silently break CI.
npx markdownlint-cli2@0.22.1 'docs/guide/en/**/*.md'
