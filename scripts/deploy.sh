#!/usr/bin/env bash
# Skrip rilis Regulasi FKIP Unsil, dijalankan di server oleh CD (.github/workflows/deploy.yml)
# atau manual. Alur: cadangan pra-migrasi -> build -> up -d (app bermigrasi) -> health check.
#
#   bash scripts/deploy.sh                    # rilis tag yang sedang di-checkout (vX.Y.Z)
#   bash scripts/deploy.sh --list             # versi yang image-nya masih ada di server
#   bash scripts/deploy.sh --rollback X.Y.Z   # kembali ke image lama tanpa build ulang
#
# Prasyarat: .env.production ada di root repo (tidak di-commit), working tree bersih.
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

BERKAS_ENV=".env.production"
COMPOSE=(docker compose --env-file "$BERKAS_ENV" -f docker-compose.prod.yml)
IMAGE_APP="regulasi-app"
IMAGE_WEB="regulasi-web"
IMAGE_BACKUP="regulasi-backup"
SIMPAN_VERSI="${SIMPAN_VERSI:-3}"
PERCOBAAN_SEHAT="${PERCOBAAN_SEHAT:-24}" # x 5 detik = 2 menit
JEDA_SEHAT=5

log() { printf '\n==> %s\n' "$*"; }
gagal() { printf 'GAGAL: %s\n' "$*" >&2; exit 1; }

[[ -f "$BERKAS_ENV" ]] || gagal "$BERKAS_ENV tidak ditemukan (salin dari .env.production.example)."

port_web() {
  local port
  port="$(sed -n 's/^WEB_PORT=\([0-9]\{1,5\}\).*/\1/p' "$BERKAS_ENV" | tail -n1)"
  echo "${port:-8034}"
}

tunggu_sehat() {
  local url i
  url="http://127.0.0.1:$(port_web)/api/health"
  log "health check -> $url"

  for ((i = 1; i <= PERCOBAAN_SEHAT; i++)); do
    if curl -fsS --max-time 10 "$url" 2>/dev/null | grep -q '"status":"ok"'; then
      printf 'OK: aplikasi sehat (percobaan %d).\n' "$i"
      return 0
    fi
    sleep "$JEDA_SEHAT"
  done

  echo "--- 50 baris terakhir log app ---" >&2
  "${COMPOSE[@]}" logs --tail=50 app >&2 || true
  gagal "/api/health tidak ok setelah $((PERCOBAAN_SEHAT * JEDA_SEHAT)) detik."
}

# Dump MariaDB (di host) sebelum container app baru menjalankan migrate --force; hasil di ./backup/mariadb pada host.
cadangan_pra_rilis() {
  if [[ -z "$("${COMPOSE[@]}" ps -q backup 2>/dev/null)" ]]; then
    log "lewati cadangan: container backup belum berjalan (deploy pertama?)"
    return 0
  fi

  log "cadangan pra-migrasi"
  "${COMPOSE[@]}" exec -T backup /usr/local/bin/backup.sh
}

daftar_versi() {
  docker image ls "$IMAGE_APP" --format '{{.Tag}}\t{{.CreatedSince}}' | grep -v '^latest' | sort -V
}

# Image lama (dan tag kode lama) disisakan agar rollback hitungan detik.
bersihkan_image() {
  local lama tag
  lama="$(docker image ls "$IMAGE_APP" --format '{{.Tag}}' | grep -v '^latest$' | sort -rV | tail -n +"$((SIMPAN_VERSI + 1))")"

  for tag in $lama; do
    docker image rm "$IMAGE_APP:$tag" "$IMAGE_WEB:$tag" "$IMAGE_BACKUP:$tag" >/dev/null 2>&1 || true
  done
  docker image prune -f >/dev/null 2>&1 || true
}

# Worker antrean dinyalakan penjadwal tiap menit (tanpa Horizon): queue:restart membuat worker yang sedang berjalan
# berhenti setelah job berjalan selesai; worker berikutnya memuat kode baru.
muat_ulang_pekerja() {
  "${COMPOSE[@]}" exec -T app php artisan queue:restart || true
}

rollback() {
  local versi="${1:?Pemakaian: deploy.sh --rollback X.Y.Z}"
  versi="${versi#v}"

  docker image inspect "$IMAGE_APP:$versi" >/dev/null 2>&1 \
    || gagal "image $IMAGE_APP:$versi tidak ada. Versi tersedia:"$'\n'"$(daftar_versi)"

  log "rollback ke $versi (tanpa build ulang)"
  APP_VERSION="$versi" "${COMPOSE[@]}" up -d --no-build --remove-orphans
  APP_VERSION="$versi" tunggu_sehat

  cat <<CATATAN

CATATAN: rollback hanya mengembalikan KODE, bukan skema basis data. Bila rilis yang gagal sempat
menjalankan migrasi, pulihkan dump terakhir (docker/backup/restore.sh, docs/DEPLOY.md bagian Cadangan dan pemulihan).
Working tree git masih di tag lama yang di-deploy; jalankan 'git checkout v$versi' bila permanen.
CATATAN
}

rilis() {
  local tag versi
  tag="$(git describe --tags --exact-match 2>/dev/null)" \
    || gagal "HEAD bukan tag rilis. Checkout tag dulu: git checkout vX.Y.Z"
  [[ "$tag" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]] || gagal "tag '$tag' bukan format vX.Y.Z."
  versi="${tag#v}"

  # Hanya kode yang sudah masuk main (lewat PR/CI) yang boleh naik ke produksi.
  if git rev-parse --verify -q origin/main >/dev/null; then
    git merge-base --is-ancestor "$tag" origin/main || gagal "tag $tag tidak berada di riwayat origin/main."
  fi

  if ! git diff --quiet || ! git diff --cached --quiet; then
    gagal "working tree kotor; bersihkan dulu."
  fi

  export APP_VERSION="$versi"
  log "rilis $tag - $(git log -1 --pretty=%s)"

  cadangan_pra_rilis

  log "build image produksi (aset Vite + vendor di-bake)"
  "${COMPOSE[@]}" build

  log "up -d (container app menjalankan migrate --force + optimize)"
  "${COMPOSE[@]}" up -d --remove-orphans

  tunggu_sehat
  muat_ulang_pekerja
  bersihkan_image

  log "status"
  "${COMPOSE[@]}" ps
  printf '\nSelesai. Rilis %s aktif.\nRollback cepat: bash scripts/deploy.sh --rollback <versi-sebelumnya>\nVersi tersedia:\n%s\n' "$tag" "$(daftar_versi)"
}

case "${1:-}" in
  --rollback) rollback "${2:-}" ;;
  --list) daftar_versi ;;
  '') rilis ;;
  *) echo "Argumen tidak dikenal: $1" >&2; exit 2 ;;
esac
