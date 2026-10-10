#!/bin/sh
# Uji pulih (host): pulihkan cadangan ke basis data UJI lewat service backup compose produksi, lalu bangun ulang
# indeks pencarian dan periksa tautan di container app yang menunjuk basis data uji.
# Pemakaian (dari root repo di server/staging): docker/backup/pulihkan-uji.sh <YYYY-MM-DD> [db_uji]
# Jangan jalankan terhadap basis data produksi: DB tujuan bawaan "regulasi_pulih".
set -eu

TANGGAL="${1:?Pemakaian: $0 <YYYY-MM-DD> [db_uji]}"
DB_UJI="${2:-regulasi_pulih}"
C="docker compose --env-file .env.production -f docker-compose.prod.yml"

if [ "$DB_UJI" = "$(grep -E '^DB_DATABASE=' .env.production | cut -d= -f2)" ]; then
    echo "DB uji tidak boleh sama dengan DB_DATABASE produksi." >&2
    exit 3
fi

$C exec -T -e DB_DATABASE="$DB_UJI" -e APP_ENV=testing backup /usr/local/bin/restore.sh "$TANGGAL"
# Kredensial root (backup) diperlukan karena DB uji berbeda dari DB aplikasi.
$C run --rm -T -e DB_DATABASE="$DB_UJI" -e DB_USERNAME=root -e DB_PASSWORD="$(grep -E '^DB_ROOT_PASSWORD=' .env.production | cut -d= -f2)" \
    -e SCOUT_PREFIX=pulih_ -e QUEUE_CONNECTION=sync -e RUN_MIGRATIONS=false app sh -c '
        php artisan regulasi:reindeks --hapus &&
        php artisan berkas:periksa-tautan'
echo "Bandingkan jumlah dengan produksi, cek pencarian, lalu hapus DB uji: DROP DATABASE \`$DB_UJI\`; dan indeks pulih_dokumen."
