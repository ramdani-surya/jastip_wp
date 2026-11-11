#!/usr/bin/env bash
# =============================================================================
# backup.sh — Backup database MySQL dan folder uploads
# =============================================================================
# Usage:
#   ./scripts/backup.sh
#
# Contoh setup cron otomatis (setiap hari jam 02:00):
#   0 2 * * * /path/to/project/scripts/backup.sh >> /var/log/tc-backup.log 2>&1
#
# Backup tersimpan di folder backups/ dengan nama bertimestamp:
#   backups/db-YYYY-MM-DD-HHMMSS.sql
#   backups/uploads-YYYY-MM-DD-HHMMSS.tar.gz
#
# Backup lebih dari 30 hari akan dihapus otomatis.
# =============================================================================

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
BACKUP_DIR="$PROJECT_DIR/backups"
TIMESTAMP="$(date +%Y-%m-%d-%H%M%S)"

cd "$PROJECT_DIR"

echo "[backup] Memulai backup — $TIMESTAMP"
mkdir -p "$BACKUP_DIR"

# Tentukan perintah docker compose yang sesuai (production vs staging/dev)
COMPOSE_CMD="docker compose"
if [ -f .env.prod ]; then
  COMPOSE_CMD="docker compose -f compose.yaml -f docker-compose.prod.yml --env-file .env.prod"
fi

# Cek apakah container db sedang berjalan. Jika belum (misal fresh install / Day-0), lewati backup.
RUNNING_DB=$($COMPOSE_CMD ps --status running -q db 2>/dev/null || true)
if [ -z "$RUNNING_DB" ]; then
  echo "[backup] Container database belum berjalan (skip backup otomatis untuk instalasi awal)."
  exit 0
fi

# -----------------------------------------------------------------------------
# 1. Database Dump
# -----------------------------------------------------------------------------
DB_FILE="$BACKUP_DIR/db-${TIMESTAMP}.sql"
echo "[backup] Melakukan dump database..."
$COMPOSE_CMD exec -T db sh -c \
  'MYSQL_PWD="$MYSQL_PASSWORD" exec mysqldump \
    --user="$MYSQL_USER" \
    --single-transaction \
    --quick \
    --lock-tables=false \
    --no-tablespaces \
    --default-character-set=utf8mb4 \
    "$MYSQL_DATABASE"' < /dev/null > "$DB_FILE"

if [ -s "$DB_FILE" ]; then
  echo "[backup] Database tersimpan: $DB_FILE ($(du -sh "$DB_FILE" | cut -f1))"
else
  echo "[backup] ERROR: Database dump kosong!" >&2
  rm -f "$DB_FILE"
  exit 1
fi

# -----------------------------------------------------------------------------
# 2. Uploads Tarball
# -----------------------------------------------------------------------------
UPLOADS_FILE="$BACKUP_DIR/uploads-${TIMESTAMP}.tar.gz"
echo "[backup] Mengarsipkan wp-content/uploads..."
if $COMPOSE_CMD exec -T wordpress tar \
    --create \
    --gzip \
    --directory /var/www/html/wp-content \
    uploads < /dev/null > "$UPLOADS_FILE" 2>/dev/null; then
  if [ -s "$UPLOADS_FILE" ]; then
    echo "[backup] Uploads tersimpan: $UPLOADS_FILE ($(du -sh "$UPLOADS_FILE" | cut -f1))"
  else
    echo "[backup] WARNING: Folder uploads kosong, file arsip dilewati."
    rm -f "$UPLOADS_FILE"
  fi
else
  echo "[backup] WARNING: Gagal mengarsipkan uploads (mungkin belum ada file). Dilewati."
  rm -f "$UPLOADS_FILE"
fi

# -----------------------------------------------------------------------------
# 3. Hapus backup lebih dari 30 hari
# -----------------------------------------------------------------------------
echo "[backup] Membersihkan backup lebih dari 30 hari..."
find "$BACKUP_DIR" -name "db-*.sql"         -mtime +30 -delete -print | sed 's/^/[backup] Dihapus: /'
find "$BACKUP_DIR" -name "uploads-*.tar.gz" -mtime +30 -delete -print | sed 's/^/[backup] Dihapus: /'

echo "[backup] Selesai — $TIMESTAMP"
