#!/bin/sh
# Cadangan harian MariaDB Regulasi FKIP Unsil (docs/DEPLOY.md §Cadangan dan pemulihan).
# Hanya basis data: berkas naskah berada di Shared Drive "Regulasi FKIP" dan sumber resmi, bukan di server;
# kamus istilah pencarian ikut tercadangkan sebagai tabel (dapat dibangun ulang dengan regulasi:bangun-kamus).
#
# Hasil: $BACKUP_DIR/<YYYY-MM-DD>/regulasi-<YYYY-MM-DD-HHMMSS>.sql.gz (+ .sha256), log $BACKUP_DIR/cadangan.log.
# Variabel: DB_HOST, DB_PORT (3306), DB_DATABASE, DB_USERNAME, DB_PASSWORD, BACKUP_DIR (/backup/mariadb),
#           RETENSI_HARI (30). Keluar non-zero bila gagal.
set -eu

: "${DB_HOST:?DB_HOST wajib diisi}"
: "${DB_DATABASE:?DB_DATABASE wajib diisi}"
: "${DB_USERNAME:?DB_USERNAME wajib diisi}"
: "${DB_PASSWORD:?DB_PASSWORD wajib diisi}"
DB_PORT="${DB_PORT:-3306}"
BACKUP_DIR="${BACKUP_DIR:-/backup/mariadb}"
RETENSI_HARI="${RETENSI_HARI:-30}"

case "$RETENSI_HARI" in
    ''|*[!0-9]*) echo "RETENSI_HARI harus bilangan bulat" >&2; exit 2 ;;
esac

mkdir -p "$BACKUP_DIR"
LOG="$BACKUP_DIR/cadangan.log"
log() { printf '%s %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*" | tee -a "$LOG"; }

TANGGAL="$(date +%Y-%m-%d)"
STEMPEL="$(date +%Y-%m-%d-%H%M%S)"
FOLDER="$BACKUP_DIR/$TANGGAL"
mkdir -p "$FOLDER"
BERKAS="$FOLDER/regulasi-$STEMPEL.sql.gz"
SEMENTARA="$BERKAS.part"

DUMP="$(command -v mariadb-dump || command -v mysqldump || true)"
if [ -z "$DUMP" ]; then
    log "GAGAL: mariadb-dump/mysqldump tidak ditemukan"
    exit 1
fi

# Kata sandi lewat berkas opsi sementara agar tidak tampak di daftar proses.
OPSI="$(mktemp)"
GAGAL="$(mktemp)"
trap 'rm -f "$OPSI" "$GAGAL" "$SEMENTARA"' EXIT
chmod 600 "$OPSI"
printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n' "$DB_HOST" "$DB_PORT" "$DB_USERNAME" "$DB_PASSWORD" > "$OPSI"

log "Mulai cadangan $DB_DATABASE@$DB_HOST → $BERKAS"

# pipefail tidak tersedia di semua sh: status dump diperiksa lewat berkas penanda.
{ "$DUMP" --defaults-extra-file="$OPSI" --single-transaction --routines --triggers --events --no-tablespaces \
    --default-character-set=utf8mb4 "$DB_DATABASE" 2>>"$LOG" || echo gagal > "$GAGAL"; } | gzip -9 > "$SEMENTARA"

if [ -s "$GAGAL" ] || [ ! -s "$SEMENTARA" ]; then
    log "GAGAL: dump basis data gagal (lihat pesan di atas)"
    exit 1
fi

gzip -t "$SEMENTARA"
mv "$SEMENTARA" "$BERKAS"
( cd "$FOLDER" && sha256sum "$(basename "$BERKAS")" > "$(basename "$BERKAS").sha256" )
log "Selesai: $BERKAS ($(du -h "$BERKAS" | cut -f1))"

# Retensi: hapus folder bertanggal yang lebih tua dari RETENSI_HARI.
find "$BACKUP_DIR" -mindepth 1 -maxdepth 1 -type d -name '[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]' -mtime +"$RETENSI_HARI" \
    -exec sh -c 'for d; do echo "Hapus cadangan lama: $d"; rm -rf "$d"; done' sh {} + | tee -a "$LOG"
