#!/usr/bin/env bash
# =============================================================================
# restore.sh — Restore database dan/atau uploads dari file backup
# =============================================================================
# Usage:
#   # Restore hanya database:
#   ./scripts/restore.sh --db backups/db-2026-10-17-020000.sql
#
#   # Restore hanya uploads:
#   ./scripts/restore.sh --uploads backups/uploads-2026-10-17-020000.tar.gz
#
#   # Restore keduanya:
#   ./scripts/restore.sh \
#     --db backups/db-2026-10-17-020000.sql \
#     --uploads backups/uploads-2026-10-17-020000.tar.gz
#
# PERINGATAN: Restore akan MENIMPA data yang ada. Pastikan Anda
# menjalankan backup terlebih dahulu sebelum melakukan restore.
# =============================================================================

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_DIR"

DB_FILE=""
UPLOADS_FILE=""

# Parse arguments
while [[ $# -gt 0 ]]; do
  case "$1" in
    --db)
      DB_FILE="$2"
      shift 2
      ;;
    --uploads)
      UPLOADS_FILE="$2"
      shift 2
      ;;
    -h|--help)
      grep '^#' "$0" | head -20 | sed 's/^# \{0,1\}//'
      exit 0
      ;;
    *)
      echo "[restore] ERROR: Argumen tidak dikenal: $1" >&2
      echo "[restore] Gunakan --help untuk bantuan." >&2
      exit 1
      ;;
  esac
done

if [[ -z "$DB_FILE" && -z "$UPLOADS_FILE" ]]; then
  echo "[restore] ERROR: Minimal satu argumen --db atau --uploads diperlukan." >&2
  echo "[restore] Contoh: $0 --db backups/db-2026-10-17-020000.sql" >&2
  exit 1
fi

# -----------------------------------------------------------------------------
# Restore Database
# -----------------------------------------------------------------------------
if [[ -n "$DB_FILE" ]]; then
  if [[ ! -f "$DB_FILE" ]]; then
    echo "[restore] ERROR: File tidak ditemukan: $DB_FILE" >&2
    exit 1
  fi

  DATABASE="$(docker compose exec -T db printenv MYSQL_DATABASE | tr -d '\r')"

  echo ""
  echo "============================================================"
  echo "  PERINGATAN: Restore Database"
  echo "============================================================"
  echo "  File  : $DB_FILE"
  echo "  Target: database '$DATABASE'"
  echo "  Semua data yang ada di database AKAN DITIMPA."
  echo "============================================================"
  printf "  Ketik nama database '%s' untuk konfirmasi: " "$DATABASE"
  read -r CONFIRM < /dev/tty

  if [[ "$CONFIRM" != "$DATABASE" ]]; then
    echo "[restore] Dibatalkan — konfirmasi tidak cocok."
    exit 1
  fi

  echo "[restore] Me-restore database dari $DB_FILE..."
  docker compose exec -T db sh -c \
    'MYSQL_PWD="$MYSQL_PASSWORD" exec mysql --user="$MYSQL_USER" "$MYSQL_DATABASE"' \
    < "$DB_FILE"
  echo "[restore] ✓ Database berhasil di-restore."
fi

# -----------------------------------------------------------------------------
# Restore Uploads
# -----------------------------------------------------------------------------
if [[ -n "$UPLOADS_FILE" ]]; then
  if [[ ! -f "$UPLOADS_FILE" ]]; then
    echo "[restore] ERROR: File tidak ditemukan: $UPLOADS_FILE" >&2
    exit 1
  fi

  echo ""
  echo "============================================================"
  echo "  PERINGATAN: Restore Uploads"
  echo "============================================================"
  echo "  File  : $UPLOADS_FILE"
  echo "  Target: /var/www/html/wp-content/uploads (di dalam container)"
  echo "  Semua file uploads yang ada AKAN DITIMPA."
  echo "============================================================"
  printf "  Lanjutkan? [y/N] "
  read -r CONFIRM < /dev/tty

  if [[ ! "$CONFIRM" =~ ^[Yy]$ ]]; then
    echo "[restore] Dibatalkan."
    exit 1
  fi

  echo "[restore] Me-restore uploads dari $UPLOADS_FILE..."
  docker compose exec -T wordpress bash -c \
    'tar --extract --gzip --directory /var/www/html/wp-content' \
    < "$UPLOADS_FILE"
  echo "[restore] ✓ Uploads berhasil di-restore."
fi

echo ""
echo "[restore] Selesai."
