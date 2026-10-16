#!/bin/sh
# Memulihkan cadangan ke lingkungan UJI (docs/DEPLOY.md §Uji pulih semester).
# Pemakaian: restore.sh <YYYY-MM-DD | berkas.sql.gz> [--paksa]
#   <YYYY-MM-DD>   memakai dump terbaru di $BACKUP_DIR/<tanggal>/
# Menolak berjalan bila APP_ENV=production kecuali --paksa. Basis data tujuan = DB_DATABASE.
# Setelah pulih jalankan di container app: php artisan regulasi:bangun-kamus && php artisan berkas:periksa-tautan
# (skrip docker/backup/pulihkan-uji.sh melakukannya otomatis untuk compose produksi).
set -eu

SUMBER="${1:-}"
PAKSA="${2:-}"
BACKUP_DIR="${BACKUP_DIR:-/backup/mariadb}"

if [ -z "$SUMBER" ]; then
    echo "Pemakaian: $0 <YYYY-MM-DD | berkas.sql.gz> [--paksa]" >&2
    exit 2
fi

if [ "${APP_ENV:-}" = "production" ] && [ "$PAKSA" != "--paksa" ]; then
    echo "Menolak memulihkan ke APP_ENV=production tanpa --paksa." >&2
    exit 3
fi

case "$SUMBER" in
    [0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9])
        BERKAS="$(ls -1 "$BACKUP_DIR/$SUMBER"/regulasi-*.sql.gz 2>/dev/null | sort | tail -n 1 || true)"
        ;;
    *)
        BERKAS="$SUMBER"
        ;;
esac

if [ -z "${BERKAS:-}" ] || [ ! -f "$BERKAS" ]; then
    echo "Cadangan tidak ditemukan: $SUMBER" >&2
    exit 2
fi

: "${DB_HOST:?DB_HOST wajib diisi}"
: "${DB_DATABASE:?DB_DATABASE wajib diisi}"
: "${DB_USERNAME:?DB_USERNAME wajib diisi}"
: "${DB_PASSWORD:?DB_PASSWORD wajib diisi}"
DB_PORT="${DB_PORT:-3306}"

if [ -f "$BERKAS.sha256" ]; then
    ( cd "$(dirname "$BERKAS")" && sha256sum -c "$(basename "$BERKAS").sha256" )
fi

gzip -t "$BERKAS"

KLIEN="$(command -v mariadb || command -v mysql)"
OPSI="$(mktemp)"
trap 'rm -f "$OPSI"' EXIT
chmod 600 "$OPSI"
printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n' "$DB_HOST" "$DB_PORT" "$DB_USERNAME" "$DB_PASSWORD" > "$OPSI"

"$KLIEN" --defaults-extra-file="$OPSI" -e "CREATE DATABASE IF NOT EXISTS \`$DB_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
gunzip -c "$BERKAS" | "$KLIEN" --defaults-extra-file="$OPSI" --default-character-set=utf8mb4 "$DB_DATABASE"

echo "Pemulihan selesai ke $DB_DATABASE@$DB_HOST dari $BERKAS"
echo "Jumlah dokumen: $("$KLIEN" --defaults-extra-file="$OPSI" -N -e "SELECT COUNT(*) FROM dokumen" "$DB_DATABASE")"
