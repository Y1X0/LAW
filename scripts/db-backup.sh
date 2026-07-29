#!/usr/bin/env bash
# نسخ احتياطي لقاعدة PostgreSQL (تطوير/تشغيلي بسيط) — راجع docs/11 §4 لاستراتيجية DR الكاملة (WAL/PITR).
# الاستخدام: scripts/db-backup.sh [backup_dir]
set -euo pipefail

BACKUP_DIR="${1:-backups}"
: "${DB_HOST:=127.0.0.1}"
: "${DB_PORT:=5432}"
: "${DB_DATABASE:=lawfirm}"
: "${DB_USERNAME:=lawfirm}"

mkdir -p "$BACKUP_DIR"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
OUT="$BACKUP_DIR/${DB_DATABASE}_${STAMP}.dump"

echo "==> pg_dump ${DB_DATABASE} → ${OUT}"
PGPASSWORD="${DB_PASSWORD:-}" pg_dump \
  --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USERNAME" \
  --format=custom --no-owner --no-privileges \
  --file="$OUT" "$DB_DATABASE"

echo "==> done: ${OUT} ($(du -h "$OUT" | cut -f1))"
# ملاحظة: في الإنتاج تُرفع النسخة إلى تخزين خارج الموقع (S3/MinIO) مشفّرة، وفق docs/11 (قاعدة 3-2-1).
