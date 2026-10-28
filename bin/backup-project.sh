#!/usr/bin/env bash
# Crée une archive complète du projet (hors dossiers de backup déjà générés).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
STAMP="$(date +%Y%m%d-%H%M%S)"
VERSION="$(tr -d '[:space:]' < "$ROOT/VERSION" 2>/dev/null || echo 'unknown')"
OUT_DIR="$ROOT/storage/backups"
NAME="sccm-packaging-factory-v${VERSION}-full-${STAMP}.tar.gz"
OUT="$OUT_DIR/$NAME"

mkdir -p "$OUT_DIR"

echo "Création du backup sources → $OUT"
tar -czf "$OUT" \
  --exclude='./storage' \
  --exclude='./backup' \
  --exclude='./docker-data' \
  --exclude='./vendor' \
  --exclude='./.phpunit.cache' \
  --exclude='./.git' \
  --exclude='./node_modules' \
  -C "$ROOT" .

SIZE="$(du -h "$OUT" | awk '{print $1}')"
echo "OK — $NAME ($SIZE)"
echo "$OUT"
