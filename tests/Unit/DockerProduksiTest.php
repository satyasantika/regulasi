<?php

use Symfony\Component\Yaml\Yaml;

/**
 * Berkas Docker produksi (F12.3): image app/web, compose, entrypoint, dan contoh .env.production.
 */
$akar = dirname(__DIR__, 2);

test('.dockerignore mengecualikan rahasia, dump, PDF, dan berkas sementara', function () use ($akar) {
    $baris = array_map('trim', file($akar.'/.dockerignore'));

    expect($baris)->toContain('.env', '.env.*', 'storage/app/tmp/*', '*.sql.gz', '*.pdf', 'vendor', 'node_modules', 'tests', 'database/seeders/data/*.local.php')
        ->not->toContain('!.env');
});

test('image produksi memuat poppler-utils, ekstensi wajib, user non-root, dan opcache produksi', function () use ($akar) {
    $dockerfile = (string) file_get_contents($akar.'/docker/Dockerfile');
    $ini = (string) file_get_contents($akar.'/docker/php/produksi.ini');

    expect($dockerfile)->toContain('poppler-utils')
        ->toContain('pdo_mysql intl bcmath gd zip pcntl')
        ->not->toMatch('/redis|horizon/i')
        ->toContain('ARG PHP_VERSION=8.5')
        ->toContain('opcache')
        ->toContain('composer install --no-dev')
        ->toContain('npm run build')
        ->toContain('ARG ASSET_URL=https://supportfkip.unsil.ac.id/regulasi')
        ->toContain('USER www-data')
        ->toContain('AS app')
        ->toContain('AS web')
        ->and($ini)->toContain('opcache.validate_timestamps = 0')
        ->toContain('display_errors = Off')
        ->toContain('expose_php = Off');
});

test('compose produksi hanya memuat app, web, scheduler, dan backup tanpa container database', function () use ($akar) {
    $kompos = Yaml::parseFile($akar.'/docker-compose.prod.yml');
    $mentah = (string) file_get_contents($akar.'/docker-compose.prod.yml');

    expect(array_keys($kompos['services']))->toEqualCanonicalizing(['php', 'web', 'scheduler', 'backup']);

    foreach (['queue', 'redis', 'mariadb', 'mysql', 'db', 'meilisearch'] as $layanan) {
        expect($kompos['services'])->not->toHaveKey($layanan);
    }

    foreach (['mariadb', 'redis', 'meilisearch'] as $volume) {
        expect($kompos['volumes'])->not->toHaveKey($volume);
    }

    // hasil parse (tanpa komentar): tidak ada image/perintah Redis, Horizon, atau Meilisearch, dan tidak ada image server DB
    expect(json_encode($kompos))->not->toMatch('/horizon|redis|meili|image":"(mariadb|mysql)/i')
        ->and($kompos['services']['scheduler']['command'])->toBe(['php', 'artisan', 'schedule:work'])
        ->and($kompos['services']['scheduler']['stop_grace_period'])->toBe('60s')
        ->and($kompos['services']['scheduler'])->not->toHaveKey('depends_on')
        ->and($kompos['services']['web']['healthcheck']['test'][1])->toContain('/api/health')
        ->and($mentah)->toContain('env_file: .env.production')
        ->toContain('tmp:/var/www/html/storage/app/tmp');
});

test('semua container terhubung ke basis data host lewat host-gateway, berbatas memori, dan lognya dirotasi', function () use ($akar) {
    $kompos = Yaml::parseFile($akar.'/docker-compose.prod.yml');

    foreach (['php', 'scheduler', 'backup'] as $nama) {
        expect($kompos['services'][$nama]['extra_hosts'])->toContain('host.docker.internal:host-gateway');
    }

    foreach ($kompos['services'] as $nama => $layanan) {
        expect($layanan)->toHaveKey('mem_limit')
            ->and($layanan['logging']['driver'])->toBe('json-file')
            ->and($layanan['logging']['options'])->toBe(['max-size' => '10m', 'max-file' => '3']);
    }

    expect($kompos['services']['backup']['environment']['DB_HOST'])->toBe('host.docker.internal');
});

test('docker/mariadb dihapus: setelan FULLTEXT server kini milik pemilik server MariaDB host', function () use ($akar) {
    expect(file_exists($akar.'/docker/mariadb/regulasi.cnf'))->toBeFalse();
});

test('entrypoint menunggu DB, bermigrasi hanya bila RUN_MIGRATIONS=true, dan menyiapkan cache', function () use ($akar) {
    $entrypoint = (string) file_get_contents($akar.'/docker/entrypoint.sh');

    expect($entrypoint)->toContain('if [ "${RUN_MIGRATIONS:-false}" = "true" ]')
        ->toContain('Menunggu basis data')
        ->toContain('php artisan migrate --force')
        ->toContain('php artisan optimize')
        ->toContain('php artisan filament:optimize')
        ->toContain('php artisan icons:cache')
        ->toContain('php artisan regulasi:bangun-kamus')
        ->not->toContain('scout:sync-index-settings')
        ->not->toMatch('/^\s*php artisan storage:link/m')
        ->and(is_executable($akar.'/docker/entrypoint.sh'))->toBeTrue();
});

test('nginx produksi memblokir dotfiles dan storage, gzip, dan batas 30M', function () use ($akar) {
    $nginx = (string) file_get_contents($akar.'/docker/nginx/default.conf');

    expect($nginx)->toContain('client_max_body_size 30M')
        ->toContain('gzip on')
        ->toContain('location ~ /\.(?!well-known)')
        ->toContain('location ^~ /storage/')
        ->toContain('fastcgi_pass php:9000')
        ->toContain('server_tokens off');
});

test('contoh .env.production memakai subpath, cookie aman, debug mati, dan tanpa rahasia', function () use ($akar) {
    $env = (string) file_get_contents($akar.'/.env.production.example');

    expect($env)->toContain('APP_ENV=production')
        ->toContain('APP_DEBUG=false')
        ->toContain('APP_VERSION=1.1.0')
        ->toContain('APP_URL=https://supportfkip.unsil.ac.id/regulasi')
        ->toContain('ASSET_URL=https://supportfkip.unsil.ac.id/regulasi')
        ->toContain('SESSION_PATH=/regulasi')
        ->toContain('SESSION_COOKIE=regulasi_session')
        ->toContain('SESSION_SECURE_COOKIE=true')
        ->toContain('SCOUT_DRIVER=database')
        ->not->toContain('MEILISEARCH')
        ->toContain('REGULASI_FT_MIN_TOKEN=3')
        ->toContain('MFA_AKTIF=true')
        ->toContain("\nDB_HOST=host.docker.internal\n")
        ->toContain("\nDB_DATABASE=db_regulasi\n")
        ->not->toMatch('/^(REDIS_|HORIZON_|MARIADB_|DB_ROOT_PASSWORD|MEILISEARCH)/m')
        ->not->toMatch('/^TRUSTED_PROXIES=\*/m')
        ->not->toMatch('/^(APP_KEY|DB_PASSWORD|REDIS_PASSWORD|MAIL_PASSWORD|MEILISEARCH_KEY|REGULASI_SUPERADMIN_PASSWORD)=\S/m');
});

test('cadangan harian 02:00 WIB dengan retensi 30 hari, log, dan pemulihan menolak produksi', function () use ($akar) {
    $dockerfile = (string) file_get_contents($akar.'/docker/backup/Dockerfile');
    $backup = (string) file_get_contents($akar.'/docker/backup/backup.sh');
    $restore = (string) file_get_contents($akar.'/docker/backup/restore.sh');
    $compose = (string) file_get_contents($akar.'/docker-compose.prod.yml');
    $gitignore = (string) file_get_contents($akar.'/.gitignore');

    expect($dockerfile)->toContain('0 2 * * *')->toContain('Asia/Jakarta')->toContain('mariadb-client')
        ->and($backup)->toContain('--single-transaction')->toContain('--routines')->toContain('gzip')
        ->toContain('RETENSI_HARI="${RETENSI_HARI:-30}"')->toContain('sha256sum')->toContain('cadangan.log')
        ->toContain('exit 1')
        ->and($restore)->toContain('"${APP_ENV:-}" = "production"')->toContain('--paksa')->toContain('sha256sum -c')
        ->and($compose)->toContain("\n  backup:")->toContain('./backup:/backup')
        ->and($gitignore)->toContain('/backup/');

    foreach (['backup.sh', 'restore.sh', 'pulihkan-uji.sh'] as $skrip) {
        expect(is_executable($akar.'/docker/backup/'.$skrip))->toBeTrue($skrip);
    }

    expect((string) file_get_contents($akar.'/docker/backup/pulihkan-uji.sh'))
        ->toContain('regulasi:bangun-kamus')
        ->not->toContain('regulasi:reindeks')
        ->not->toContain('DB_ROOT_PASSWORD')
        ->toContain('berkas:periksa-tautan');
});

test('auto-pull: deploy dipicu tag rilis, lolos uji penuh, dan skrip rilis menjaga cadangan, kesehatan, rollback', function () use ($akar) {
    $alur = (string) file_get_contents($akar.'/.github/workflows/deploy.yml');
    $ci = (string) file_get_contents($akar.'/.github/workflows/ci.yml');
    $skrip = (string) file_get_contents($akar.'/scripts/deploy.sh');

    expect($alur)->toContain("tags: [ 'v[0-9]+.[0-9]+.[0-9]+' ]")
        ->toContain('needs: uji')
        ->toContain('uses: ./.github/workflows/ci.yml')
        ->toContain('cancel-in-progress: false')
        ->toContain('secrets.SSH_PRIVATE_KEY')
        ->toContain('cd ~/docker-apps/regulasi')
        ->toContain('git fetch --tags')
        ->toContain('bash scripts/deploy.sh')
        ->not->toContain('branches:')
        ->not->toMatch('/BEGIN [A-Z ]*PRIVATE KEY/')
        ->and($ci)->toContain('workflow_call:')->toContain('scripts/*.sh')
        ->and($skrip)->toContain('set -euo pipefail')
        ->toContain('IMAGE_APP="regulasi-app"')
        ->toContain('git merge-base --is-ancestor')
        ->toContain('backup.sh')
        ->toContain('/api/health')
        ->toContain('queue:restart')
        ->not->toContain('horizon')
        ->toContain('--rollback')
        ->toContain('--no-build')
        ->not->toContain('--no-verify')
        ->and(is_executable($akar.'/scripts/deploy.sh'))->toBeTrue();
});
