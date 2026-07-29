#!/usr/bin/env bash
# استعادة قاعدة PostgreSQL من ملف نسخة (custom format) — راجع docs/11 §4.
# الاستخدام: scripts/db-restore.sh <backup_file.dump>
set -euo pipefail

FILE="${1:?يجب تمرير مسار ملف النسخة الاحتياطية}"
: "${DB_HOST:=127.0.0.1}"
: "${DB_PORT:=5432}"
: "${DB_DATABASE:=lawfirm}"
: "${DB_USERNAME:=lawfirm}"

echo "==> pg_restore ${FILE} → ${DB_DATABASE}"
PGPASSWORD="${DB_PASSWORD:-}" pg_restore \
  --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USERNAME" \
  --dbname="$DB_DATABASE" --clean --if-exists --no-owner --no-privileges \
  "$FILE"

echo "==> restore complete"
