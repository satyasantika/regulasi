#!/bin/sh
# Entrypoint container app/queue/scheduler Regulasi FKIP Unsil.
# - Menunggu basis data siap (maks. DB_TUNGGU_DETIK, bawaan 60).
# - Migrasi & sinkron pengaturan indeks Meilisearch hanya bila RUN_MIGRATIONS=true (container app).
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
    # Pengaturan indeks (filter, sort, sinonim). Gagal tidak menghentikan aplikasi: pencarian memakai cadangan
    # FULLTEXT sampai Meilisearch sehat (02-ARSITEKTUR §5.2).
    php artisan scout:sync-index-settings || echo "Peringatan: scout:sync-index-settings gagal; jalankan ulang setelah Meilisearch sehat." >&2
fi

exec "$@"
