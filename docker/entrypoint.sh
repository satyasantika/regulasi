#!/bin/sh
# Entrypoint container app/scheduler Regulasi FKIP Unsil.
# - Menunggu basis data siap di host (maks. DB_TUNGGU_DETIK, bawaan 60).
# - Migrasi & pembangunan kamus istilah pencarian hanya bila RUN_MIGRATIONS=true (container app).
# - Cache config/route/view/event + komponen Filament & ikon. storage:link TIDAK dipakai (tidak ada berkas publik).
set -eu

cd /var/www/html

tunggu="${DB_TUNGGU_DETIK:-60}"
until php -r '
    $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s", getenv("DB_HOST") ?: "127.0.0.1", getenv("DB_PORT") ?: "3306", getenv("DB_DATABASE") ?: "");
    try { new PDO($dsn, getenv("DB_USERNAME") ?: "", getenv("DB_PASSWORD") ?: "", [PDO::ATTR_TIMEOUT => 3]); exit(0); } catch (Throwable $e) { exit(1); }
' 2>/dev/null; do
    tunggu=$((tunggu - 2))
    if [ "$tunggu" -le 0 ]; then
        echo "Basis data tidak dapat dijangkau." >&2
        exit 1
    fi
    echo "Menunggu basis data ..."
    sleep 2
done

php artisan optimize
php artisan filament:optimize
php artisan icons:cache

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
    # Kamus istilah (koreksi salah ketik, sinonim). Gagal tidak menghentikan aplikasi: pencarian FULLTEXT tetap
    # berjalan tanpa koreksi sampai kamus terbangun (02-ARSITEKTUR §5.1).
    php artisan regulasi:bangun-kamus || echo "Peringatan: regulasi:bangun-kamus gagal; jalankan ulang secara manual." >&2
fi

exec "$@"
