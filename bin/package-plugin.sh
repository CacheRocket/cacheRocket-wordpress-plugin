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
# WordPress.org rejects hidden files (e.g. .gitignore). Keep the archive free of dotfiles.
rsync -a \
  --exclude='.*' \
  --exclude='*.md' \
  --exclude='bin' \
  --exclude='sample-data' \
  --exclude='tests' \
  --exclude='languages/_build' \
  --exclude='phpcs.xml' \
  --exclude='phpcs.xml.dist' \
  --exclude='composer.json' \
  --exclude='composer.lock' \
  --exclude='package.json' \
  --exclude='package-lock.json' \
  "$ROOT/" "$STAGE/"

rm -f "$OUT"
(
  cd "$WORKDIR"
  zip -r "$OUT" cacherocket -x '*/.*' -x '*.DS_Store'
)
rm -rf "$WORKDIR"

echo "Wrote $OUT"
echo "Run Plugin Check against this zip (folder slug: cacherocket)."
echo "Checking the repo folder cacheRocket-wordpress-plugin will fail TextDomainMismatch."
