<?php

$akar = dirname(__DIR__, 2);

test('README representatif tanpa rujukan alat bantu penulisan kode', function () use ($akar) {
    $readme = (string) file_get_contents($akar.'/README.md');

    expect($readme)->toContain('## Fitur')
        ->toContain('## Tumpukan teknologi')
        ->toContain('## Menjalankan lokal')
        ->toContain('## Deploy')
        ->toContain('## Lisensi & kontak')
        ->not->toMatch('/claude|vibecod|anthropic|\bagen\b|\bAI\b|kecerdasan buatan/i');
});

test('CHANGELOG memuat rilis 1.1.0 dan ADR 0002 terdaftar', function () use ($akar) {
    expect((string) file_get_contents($akar.'/CHANGELOG.md'))->toContain('## [1.1.0] - 2026-10-12')
        ->and((string) file_get_contents($akar.'/docs/keputusan/README.md'))->toContain('0002-flag-mfa-aktif.md')
        ->and(is_file($akar.'/docs/keputusan/0002-flag-mfa-aktif.md'))->toBeTrue();
});
