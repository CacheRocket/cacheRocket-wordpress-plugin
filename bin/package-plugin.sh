#!/usr/bin/env bash
# Package the WordPress plugin for Plugin Check / WordPress.org.
# The zip root folder MUST be `cacherocket` so the text domain matches the slug.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST_ROOT="$(cd "$ROOT/.." && pwd)"
WORKDIR="$(mktemp -d)"
STAGE="$WORKDIR/cacherocket"
OUT="${DIST_ROOT}/cacherocket.zip"

mkdir -p "$STAGE"
rsync -a \
  --exclude='.git' \
  --exclude='.DS_Store' \
  --exclude='.distignore' \
  --exclude='*.md' \
  --exclude='bin' \
  --exclude='tests' \
  --exclude='languages/_build' \
  --exclude='.phpcs.xml' \
  --exclude='phpcs.xml' \
  --exclude='phpcs.xml.dist' \
  "$ROOT/" "$STAGE/"

rm -f "$OUT"
(
  cd "$WORKDIR"
  zip -r "$OUT" cacherocket -x '*.DS_Store'
)
rm -rf "$WORKDIR"

echo "Wrote $OUT"
echo "Run Plugin Check against this zip (folder slug: cacherocket)."
echo "Checking the repo folder cacheRocket-wordpress-plugin will fail TextDomainMismatch."
