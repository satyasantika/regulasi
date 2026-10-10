<?php

use App\Enums\JenisTautan;
use App\Enums\PenyediaTautan;
use App\Enums\Peran;
use App\Enums\StatusPublikasi;
use App\Models\Dokumen;
use App\Models\KategoriSistem;
use App\Models\LogBukaTautan;
use App\Models\TautanBerkas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const ID_DRIVE_BUKA = '1ZyXwVuTsRqPoNmLkJiHgFeDcBa98765';

beforeEach(function () {
    RateLimiter::clear('buka');
});

function tautanUntuk(Dokumen $dokumen): TautanBerkas
{
    return TautanBerkas::create([
        'pemilik_type' => 'dokumen',
        'pemilik_id' => $dokumen->id,
        'jenis' => JenisTautan::NaskahUtama,
        'url' => 'https://drive.google.com/file/d/'.ID_DRIVE_BUKA.'/view',
        'penyedia' => PenyediaTautan::GoogleDrive,
        'drive_file_id' => ID_DRIVE_BUKA,
    ]);
}

function urlBuka(TautanBerkas $tautan): string
{
    return "/d/{$tautan->id}/buka";
}

test('tamu membuka dokumen publik terbit: 302 ke Drive, log, dan penghitung bertambah', function () {
    $dokumen = Dokumen::factory()->terbit()->create();
    $tautan = tautanUntuk($dokumen);

    $this->get(urlBuka($tautan), ['User-Agent' => str_repeat('A', 300)])
        ->assertStatus(302)
        ->assertRedirect($tautan->url)
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('Cache-Control', 'no-store, private');

    $log = LogBukaTautan::firstOrFail();

    expect($log->dokumen_id)->toBe($dokumen->id)
        ->and($log->tautan_berkas_id)->toBe($tautan->id)
        ->and($log->user_id)->toBeNull()
        ->and($log->saluran->value)->toBe('web')
        ->and(strlen((string) $log->user_agent))->toBe(255)
        ->and($tautan->refresh()->jumlah_dibuka)->toBe(1)
        ->and($dokumen->refresh()->jumlah_dibuka)->toBe(1);
});

test('IP mentah tidak tersimpan, hanya hash SHA-256 IP + APP_KEY', function () {
    $tautan = tautanUntuk(Dokumen::factory()->terbit()->create());

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->get(urlBuka($tautan))->assertStatus(302);

    $log = LogBukaTautan::firstOrFail();

    expect($log->ip_hash)->toBe(hash('sha256', '203.0.113.7'.config('app.key')))
        ->and(strlen($log->ip_hash))->toBe(64)
        ->and(json_encode($log->getAttributes()))->not->toContain('203.0.113.7');
});

test('tamu ke dokumen terbit internal dialihkan ke halaman masuk', function () {
    $tautan = tautanUntuk(Dokumen::factory()->terbit()->internal()->create());

    $this->get(urlBuka($tautan))
        ->assertRedirect(route('filament.akun.auth.login'))
        ->assertSessionHas('url.intended', url(urlBuka($tautan)));

    expect(LogBukaTautan::count())->toBe(0);
});

test('tamu ke draf, diarsipkan, atau menunggu verifikasi mendapat 404', function (StatusPublikasi $status) {
    $tautan = tautanUntuk(Dokumen::factory()->berstatus($status)->create());

    $this->get(urlBuka($tautan))->assertNotFound();
})->with([StatusPublikasi::Draf, StatusPublikasi::Diarsipkan, StatusPublikasi::MenungguVerifikasi]);

test('pengguna login membuka dokumen internal terbit', function () {
    $pengguna = penggunaDenganPeran(Peran::Pengguna);
    $tautan = tautanUntuk(Dokumen::factory()->terbit()->internal()->create());

    $this->actingAs($pengguna)->get(urlBuka($tautan))->assertRedirect($tautan->url);

    expect(LogBukaTautan::firstOrFail()->user_id)->toBe($pengguna->id);
});

test('pengguna login tidak dapat membuka draf', function () {
    $tautan = tautanUntuk(Dokumen::factory()->create());

    $this->actingAs(penggunaDenganPeran(Peran::Pengguna))->get(urlBuka($tautan))->assertNotFound();
});

test('kontributor dapat membuka draf miliknya dari panel admin', function () {
    $kontributor = penggunaDenganPeran(Peran::Kontributor);
    $kategori = KategoriSistem::factory()->create();
    $kontributor->kategoriSistem()->attach($kategori);
    $dokumen = Dokumen::factory()->create(['dibuat_oleh' => $kontributor->id]);
    $dokumen->kategoriSistem()->attach($kategori);
    $tautan = tautanUntuk($dokumen);

    $this->actingAs($kontributor)->get(urlBuka($tautan))->assertRedirect($tautan->url);

    $lain = penggunaDenganPeran(Peran::Kontributor);
    $this->actingAs($lain)->get(urlBuka($tautan))->assertNotFound();
});

test('UUID acak dan id tidak sah mendapat 404', function () {
    $this->get('/d/'.Str::uuid7().'/buka')->assertNotFound();
    $this->get('/d/123/buka')->assertNotFound();
});

test('tautan yang dihapus lunak tidak dapat dibuka', function () {
    $tautan = tautanUntuk(Dokumen::factory()->terbit()->create());
    $tautan->delete();

    $this->get(urlBuka($tautan))->assertNotFound();
});

test('permintaan ke-31 dalam semenit mendapat 429', function () {
    $tautan = tautanUntuk(Dokumen::factory()->terbit()->create());

    for ($i = 0; $i < 30; $i++) {
        $this->get(urlBuka($tautan))->assertStatus(302);
    }

    $this->get(urlBuka($tautan))->assertStatus(429);
});

test('parameter tujuan tidak mengubah redirect (bebas open redirect)', function () {
    $tautan = tautanUntuk(Dokumen::factory()->terbit()->create());

    $this->get(urlBuka($tautan).'?to=https://evil.com&url=https://evil.com&redirect=https://evil.com')
        ->assertRedirect($tautan->url);
});

test('komponen tautan berkas tidak mencetak URL Drive dokumen internal untuk tamu', function () {
    $tautan = tautanUntuk(Dokumen::factory()->terbit()->internal()->create());

    $html = (string) $this->blade('<x-tautan-berkas :tautan="$tautan" />', ['tautan' => $tautan]);

    expect($html)->not->toContain(ID_DRIVE_BUKA)
        ->and($html)->not->toContain('drive.google.com')
        ->and($html)->toContain(route('tautan.buka', $tautan))
        ->and($html)->toContain('Masuk untuk membuka berkas');
});

test('komponen tautan berkas menampilkan pratinjau Drive bagi yang berhak', function () {
    $tautan = tautanUntuk(Dokumen::factory()->terbit()->create());

    $html = (string) $this->blade('<x-tautan-berkas :tautan="$tautan" />', ['tautan' => $tautan]);

    expect($html)->toContain('https://drive.google.com/file/d/'.ID_DRIVE_BUKA.'/preview')
        ->and($html)->toContain('Buka berkas')
        ->and($html)->not->toContain('/view');
});

test('komponen tidak mencetak URL draf untuk pengguna yang tidak berhak', function () {
    $tautan = tautanUntuk(Dokumen::factory()->create());

    $this->actingAs(penggunaDenganPeran(Peran::Pengguna));
    $html = (string) $this->blade('<x-tautan-berkas :tautan="$tautan" />', ['tautan' => $tautan]);

    expect($html)->not->toContain(ID_DRIVE_BUKA);
});
