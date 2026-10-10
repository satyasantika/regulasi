<?php

use App\Actions\Relasi\TambahRelasiDokumen;
use App\Enums\JenisRelasi;
use App\Enums\JenisTautan;
use App\Enums\PenyediaTautan;
use App\Enums\Peran;
use App\Enums\StatusBerlaku;
use App\Enums\StatusPublikasi;
use App\Jobs\CatatBukaTautan;
use App\Models\BadanPenetap;
use App\Models\Dokumen;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use App\Models\KlienApi;
use App\Models\LogBukaTautan;
use App\Models\TautanBerkas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    RateLimiter::clear('buka');

    $this->aset = KategoriSistem::factory()->create(['kode' => 'aset', 'nama' => 'Aset', 'urutan' => 1]);
    $this->sdm = KategoriSistem::factory()->create(['kode' => 'sdm', 'nama' => 'SDM', 'urutan' => 2]);
    $this->permen = JenisDokumen::factory()->create(['kode' => 'PERMEN', 'nama' => 'Peraturan Menteri']);
    $this->pp = JenisDokumen::factory()->create(['kode' => 'PP', 'nama' => 'Peraturan Pemerintah']);
    $this->kemenkeu = BadanPenetap::factory()->create(['nama' => 'Kementerian Keuangan', 'teu' => 'Indonesia. Kementerian Keuangan']);
});

/**
 * Token klien API; $internal menambah ability dokumen:baca-internal.
 */
function tokenKlien(bool $internal = false, ?array $abilities = null): string
{
    $klien = KlienApi::factory()->create();

    return $klien->createToken('uji', $abilities ?? ($internal ? ['dokumen:baca', 'dokumen:baca-internal'] : ['dokumen:baca']))->plainTextToken;
}

/**
 * @param  array<string, mixed>  $atribut
 */
function dokumenApi(array $atribut = [], array $kategori = [], ?string $state = 'terbit'): Dokumen
{
    $pabrik = Dokumen::factory();
    $pabrik = $state === 'terbit' ? $pabrik->terbit() : $pabrik;
    $dokumen = $pabrik->create($atribut);
    $dokumen->kategoriSistem()->sync(collect($kategori)->pluck('id')->all());

    return $dokumen;
}

function tautanApi(Dokumen $dokumen, string $url = 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUvWxYz012345/view'): TautanBerkas
{
    return TautanBerkas::create([
        'pemilik_type' => 'dokumen',
        'pemilik_id' => $dokumen->id,
        'jenis' => JenisTautan::NaskahUtama,
        'url' => $url,
        'penyedia' => PenyediaTautan::GoogleDrive,
        'drive_file_id' => Str::between($url, '/d/', '/view'),
    ]);
}

function getApi(string $url, string $token): TestResponse
{
    app('auth')->forgetGuards();

    return test()->getJson($url, ['Authorization' => 'Bearer '.$token]);
}

test('tanpa token 401 berformat JSON:API', function () {
    $this->getJson('/api/v1/dokumen')
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertExactJson(['errors' => [['status' => '401', 'title' => 'Tidak terautentikasi']]]);

    // Tanpa Accept JSON pun tetap 401 JSON (bukan redirect ke login).
    $this->get('/api/v1/dokumen')->assertUnauthorized()->assertJsonPath('errors.0.status', '401');
});

test('token tanpa ability dokumen:baca 403', function () {
    getApi('/api/v1/dokumen', tokenKlien(abilities: ['lain']))
        ->assertForbidden()
        ->assertJsonPath('errors.0.status', '403');
});

test('filter kategori dan status memakai kode; alias sederhana memberi hasil sama', function () {
    $pmk = dokumenApi(['sebutan_singkat' => 'PMK 181/2016', 'nomor' => '181/PMK.06/2016', 'tahun' => 2016, 'jenis_dokumen_id' => $this->permen->id, 'badan_penetap_id' => $this->kemenkeu->id], [$this->aset]);
    $pp = dokumenApi(['sebutan_singkat' => 'PP 28/2020', 'tahun' => 2020, 'jenis_dokumen_id' => $this->pp->id], [$this->aset]);
    dokumenApi(['sebutan_singkat' => 'PP 27/2014', 'tahun' => 2014, 'status_berlaku' => StatusBerlaku::Diubah], [$this->aset]);
    dokumenApi(['sebutan_singkat' => 'Permen SDM', 'tahun' => 2021], [$this->sdm]);
    $token = tokenKlien();

    $respons = getApi('/api/v1/dokumen?filter[kategori]=aset&filter[status]=berlaku', $token)
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2)
        // Bawaan urut -tahun.
        ->assertJsonPath('data.0.id', $pp->id)
        ->assertJsonPath('data.1.id', $pmk->id)
        ->assertJsonPath('data.1.type', 'dokumen')
        ->assertJsonPath('data.1.attributes.sebutan_singkat', 'PMK 181/2016')
        ->assertJsonPath('data.1.attributes.jenis', ['kode' => 'PERMEN', 'nama' => 'Peraturan Menteri'])
        ->assertJsonPath('data.1.attributes.badan_penetap', ['nama' => 'Kementerian Keuangan', 'teu' => 'Indonesia. Kementerian Keuangan'])
        ->assertJsonPath('data.1.attributes.status_berlaku', 'berlaku')
        ->assertJsonPath('data.1.links.self', route('api.v1.dokumen.show', $pmk->id))
        ->assertJsonPath('data.1.links.html', route('dokumen.show', ['dokumen' => $pmk->id, 'slug' => $pmk->slug]));

    $alias = getApi('/api/v1/dokumen?kategori=aset&status=berlaku', $token)->assertOk();
    expect($alias->json('data'))->toBe($respons->json('data'));

    // Koma = OR; filter jenis & tahun.
    getApi('/api/v1/dokumen?filter[kategori]=aset,sdm', $token)->assertJsonPath('meta.total', 4);
    getApi('/api/v1/dokumen?filter[jenis]=PERMEN&filter[tahun]=2016', $token)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $pmk->id);
    getApi('/api/v1/dokumen?filter[badan]='.$this->kemenkeu->slug, $token)->assertJsonCount(1, 'data');

    // q memakai layanan pencarian (nomor berawalan).
    getApi('/api/v1/dokumen?q=181/PMK', $token)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $pmk->id);

    // sort tahun naik.
    getApi('/api/v1/dokumen?filter[kategori]=aset&sort=tahun', $token)->assertJsonPath('data.0.attributes.sebutan_singkat', 'PP 27/2014');
});

test('id dokumen berupa UUIDv7 36 karakter', function () {
    dokumenApi();

    $id = getApi('/api/v1/dokumen', tokenKlien())->assertOk()->json('data.0.id');

    expect(strlen($id))->toBe(36)
        ->and(Str::isUuid($id))->toBeTrue()
        ->and($id[14])->toBe('7');
});

test('dokumen internal hanya untuk token berability dokumen:baca-internal', function () {
    $publik = dokumenApi();
    $internal = dokumenApi(['visibilitas' => 'internal']);

    getApi('/api/v1/dokumen', tokenKlien())->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $publik->id);
    getApi("/api/v1/dokumen/{$internal->id}", tokenKlien())->assertNotFound()->assertJsonPath('errors.0.status', '404');

    $tokenInternal = tokenKlien(internal: true);
    getApi('/api/v1/dokumen', $tokenInternal)->assertJsonCount(2, 'data');
    getApi("/api/v1/dokumen/{$internal->id}", $tokenInternal)->assertOk()->assertJsonPath('data.attributes.visibilitas', 'internal');
});

test('draf dan menunggu verifikasi tidak pernah muncul, juga bagi token internal', function () {
    $draf = dokumenApi(state: null);
    $menunggu = Dokumen::factory()->berstatus(StatusPublikasi::MenungguVerifikasi)->create();
    $token = tokenKlien(internal: true);

    getApi('/api/v1/dokumen', $token)->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
    getApi("/api/v1/dokumen/{$draf->id}", $token)->assertNotFound();
    getApi("/api/v1/dokumen/{$menunggu->id}", $token)->assertNotFound();
});

test('include=relasi memuat dicabut_oleh untuk Permendikbud 3/2021', function () {
    $permen3 = dokumenApi(['sebutan_singkat' => 'Permendikbud 3/2021', 'tahun' => 2021, 'tanggal_penetapan' => '2021-01-28']);
    $permen42 = dokumenApi(['sebutan_singkat' => 'Permendiktisaintek 42/2025', 'tahun' => 2025, 'tanggal_penetapan' => '2025-10-15']);
    app(TambahRelasiDokumen::class)->handle($permen42, $permen3, JenisRelasi::Mencabut, null, penggunaDenganPeran(Peran::AdminRegulasi));

    $respons = getApi("/api/v1/dokumen/{$permen3->id}?include=relasi,kategori", tokenKlien())
        ->assertOk()
        ->assertJsonPath('data.attributes.status_berlaku', 'dicabut')
        ->assertJsonCount(1, 'data.relationships.relasi.data')
        ->assertJsonPath('data.relationships.relasi.data.0.type', 'relasi');

    $relasi = collect($respons->json('included'))->firstWhere('type', 'relasi');
    expect($relasi['attributes']['jenis_relasi'])->toBe('dicabut_oleh')
        ->and($relasi['attributes']['efektif'])->toBeTrue()
        ->and($relasi['attributes']['dokumen_terkait'])->toBe([
            'id' => $permen42->id,
            'sebutan_singkat' => 'Permendiktisaintek 42/2025',
            'status_berlaku' => 'berlaku',
        ]);

    // Tanpa include: tanpa relationships & included.
    getApi("/api/v1/dokumen/{$permen3->id}", tokenKlien())->assertJsonMissingPath('data.relationships')->assertJsonMissingPath('included');
});

test('include=kategori pada daftar: included unik per type+id', function () {
    dokumenApi([], [$this->aset]);
    dokumenApi([], [$this->aset, $this->sdm]);

    $respons = getApi('/api/v1/dokumen?include=kategori', tokenKlien())->assertOk();
    $included = collect($respons->json('included'));

    expect($included)->toHaveCount(2)
        ->and($included->pluck('type')->unique()->all())->toBe(['kategori-sistem'])
        ->and($included->pluck('attributes.kode')->sort()->values()->all())->toBe(['aset', 'sdm']);
});

test('include tidak dikenal atau sort tidak valid → 422 JSON:API', function () {
    $token = tokenKlien();

    getApi('/api/v1/dokumen?include=pembuat', $token)
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('errors.0.status', '422')
        ->assertJsonPath('errors.0.source.parameter', 'include');

    getApi('/api/v1/dokumen?sort=judul', $token)->assertUnprocessable()->assertJsonPath('errors.0.source.parameter', 'sort');
    getApi('/api/v1/dokumen?page[number]=0', $token)->assertUnprocessable()->assertJsonPath('errors.0.source.parameter', 'page[number]');
});

test('tautan dokumen publik memakai /d/{id}/buka tanpa URL mentah', function () {
    $dokumen = dokumenApi();
    $tautan = tautanApi($dokumen);

    $respons = getApi("/api/v1/dokumen/{$dokumen->id}?include=tautan", tokenKlien())->assertOk();
    $objek = collect($respons->json('included'))->firstWhere('type', 'tautan');

    expect($objek['id'])->toBe($tautan->id)
        ->and($objek['attributes'])->toMatchArray(['jenis' => 'naskah_utama', 'penyedia' => 'google_drive', 'status_cek' => 'belum', 'jumlah_halaman' => null])
        ->and($objek['links']['buka'])->toBe(route('tautan.buka', ['tautanBerkas' => $tautan->id]))
        ->and($respons->getContent())->not->toContain('drive.google.com');
});

test('tautan dokumen internal bertanda tangan 10 menit, mencatat klien API, lalu kedaluwarsa', function () {
    $this->freezeSecond();
    $dokumen = dokumenApi(['visibilitas' => 'internal']);
    $tautan = tautanApi($dokumen);
    $klien = KlienApi::factory()->create();
    $token = $klien->createToken('uji', ['dokumen:baca', 'dokumen:baca-internal'])->plainTextToken;

    $respons = getApi("/api/v1/dokumen/{$dokumen->id}?include=tautan", $token)->assertOk();
    $buka = collect($respons->json('included'))->firstWhere('type', 'tautan')['links']['buka'];

    // URL mentah internal tidak pernah ada di JSON.
    expect($respons->getContent())->not->toContain('drive.google.com')->not->toContain('1AbCdEfGhIjKlMnOpQrStUvWxYz012345')
        ->and($buka)->toContain('/buka-api')->toContain('signature=')->toContain('expires=');

    $this->get($buka)->assertRedirect($tautan->url);

    $log = LogBukaTautan::query()->sole();
    expect($log->saluran->value)->toBe('api')
        ->and($log->klien_api_id)->toBe($klien->id)
        ->and($log->user_id)->toBeNull();

    // URL yang diubah ditolak; setelah 10 menit kedaluwarsa. Keduanya tidak dicatat.
    Bus::fake();
    $this->get(str_replace($klien->id, (string) Str::uuid7(), $buka))->assertForbidden();

    $this->travel(9)->minutes();
    $this->get($buka)->assertRedirect($tautan->url);

    $this->travel(2)->minutes();
    $this->get($buka)->assertForbidden();
    Bus::assertDispatchedAfterResponseTimes(CatatBukaTautan::class, 1);
});

test('page[size]=500 dibatasi 100 dan tautan paginasi tersedia', function () {
    Dokumen::factory()->terbit()->count(3)->create();

    getApi('/api/v1/dokumen?page[size]=500', tokenKlien())
        ->assertOk()
        ->assertJsonPath('meta.page.size', 100)
        ->assertJsonPath('meta.page.number', 1);

    $respons = getApi('/api/v1/dokumen?page[size]=2', tokenKlien())->assertJsonCount(2, 'data')->assertJsonPath('meta.page.last', 2);
    expect(urldecode($respons->json('links.next')))->toContain('page[number]=2')->toContain('page[size]=2');
});

test('jenis-dokumen dan kategori-sistem: aktif, terurut, dengan jumlah dokumen', function () {
    dokumenApi(['jenis_dokumen_id' => $this->permen->id], [$this->aset]);
    dokumenApi(['jenis_dokumen_id' => $this->permen->id, 'visibilitas' => 'internal'], [$this->aset]);
    dokumenApi(['jenis_dokumen_id' => $this->permen->id], [$this->aset], state: null);
    KategoriSistem::factory()->create(['kode' => 'mati', 'aktif' => false]);
    $token = tokenKlien();

    $kategori = getApi('/api/v1/kategori-sistem', $token)
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.0.type', 'kategori-sistem')
        ->assertJsonPath('data.0.attributes.kode', 'aset')
        ->assertJsonPath('data.0.meta.jumlah_dokumen', 1)
        ->assertJsonPath('data.1.attributes.kode', 'sdm')
        ->json('data');
    expect(collect($kategori)->pluck('attributes.kode'))->not->toContain('mati');

    $jenis = collect(getApi('/api/v1/jenis-dokumen', $token)->assertOk()->json('data'))->firstWhere('attributes.kode', 'PERMEN');
    expect($jenis['type'])->toBe('jenis-dokumen')->and($jenis['meta']['jumlah_dokumen'])->toBe(1);

    $jenisInternal = collect(getApi('/api/v1/jenis-dokumen', tokenKlien(internal: true))->json('data'))->firstWhere('attributes.kode', 'PERMEN');
    expect($jenisInternal['meta']['jumlah_dokumen'])->toBe(2);
});
