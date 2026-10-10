<?php

use App\Enums\KelompokJenisDokumen;
use App\Enums\Peran;
use App\Enums\StatusPublikasi;
use App\Models\BadanPenetap;
use App\Models\Dokumen;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use App\Support\CacheRegulasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

function urlKanonik(Dokumen $dokumen): string
{
    return route('dokumen.show', ['dokumen' => $dokumen->id, 'slug' => $dokumen->slug]);
}

/**
 * @return array<string, mixed>
 */
function jsonLd(string $html): array
{
    expect(preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $cocok))->toBe(1);

    return json_decode($cocok[1], true, flags: JSON_THROW_ON_ERROR);
}

test('sitemap memuat dokumen publik terbit, jenis & kategori berdokumen, dan tidak memuat internal/draf/diarsipkan', function () {
    $jenis = JenisDokumen::factory()->create(['slug' => 'jenis-sitemap']);
    $jenisKosong = JenisDokumen::factory()->create(['slug' => 'jenis-kosong']);
    $kategori = KategoriSistem::factory()->create(['slug' => 'kategori-sitemap']);

    $publik = Dokumen::factory()->terbit()->create(['jenis_dokumen_id' => $jenis->id]);
    $publik->kategoriSistem()->attach($kategori);
    $internal = Dokumen::factory()->terbit()->internal()->create();
    $draf = Dokumen::factory()->create(['jenis_dokumen_id' => $jenisKosong->id]);
    $arsip = Dokumen::factory()->berstatus(StatusPublikasi::Diarsipkan)->create();

    $respons = $this->get(route('sitemap'))->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    $xml = (string) $respons->getContent();

    $dom = new DOMDocument;
    expect($dom->loadXML($xml))->toBeTrue()
        ->and($xml)->toContain('<loc>'.urlKanonik($publik).'</loc><lastmod>')
        ->toContain('<loc>'.route('dokumen.index').'</loc>')
        ->toContain('<loc>'.route('dokumen.jenis', 'jenis-sitemap').'</loc>')
        ->toContain('<loc>'.route('dokumen.kategori', 'kategori-sitemap').'</loc>')
        ->not->toContain($internal->id)
        ->not->toContain($draf->id)
        ->not->toContain($arsip->id)
        ->not->toContain('jenis-kosong');
});

test('sitemap di-cache dan dibersihkan oleh lupakanPublik', function () {
    $this->get(route('sitemap'))->assertOk();
    expect(Cache::has('sitemap'))->toBeTrue();

    $baru = Dokumen::factory()->terbit()->create();
    $this->get(route('sitemap'))->assertDontSee($baru->id);

    CacheRegulasi::lupakanPublik();

    expect(Cache::has('sitemap'))->toBeFalse();
    $this->get(route('sitemap'))->assertSee($baru->id);
});

test('halaman dokumen internal memiliki noindex, dokumen publik tidak', function () {
    $internal = Dokumen::factory()->terbit()->internal()->create();
    $publik = Dokumen::factory()->terbit()->create();

    $this->actingAs(penggunaDenganPeran(Peran::Pengguna))
        ->get(urlKanonik($internal))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

    $this->get(urlKanonik($publik))
        ->assertOk()
        ->assertDontSee('name="robots"', false);
});

test('panel admin dan akun memiliki noindex', function () {
    $this->get('/admin/login')->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    $this->get('/akun/login')->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

test('canonical memakai slug terkini, deskripsi dan open graph tersedia', function () {
    $dokumen = Dokumen::factory()->terbit()->create(['abstrak' => 'Ringkasan dokumen untuk mesin pencari.']);

    $this->get(urlKanonik($dokumen))
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.urlKanonik($dokumen).'">', false)
        ->assertSee('<meta name="description" content="Ringkasan dokumen untuk mesin pencari.">', false)
        ->assertSee('<meta property="og:url" content="'.urlKanonik($dokumen).'">', false)
        ->assertSee('<meta property="og:type" content="article">', false);

    $this->get(route('dokumen.index').'?q=apa')
        ->assertSee('<link rel="canonical" href="'.route('dokumen.index').'">', false);
});

test('JSON-LD peraturan bertipe Legislation dan valid JSON', function () {
    $jenis = JenisDokumen::factory()->create(['kelompok' => KelompokJenisDokumen::Peraturan, 'nama' => 'Peraturan Menteri']);
    $badan = BadanPenetap::factory()->create(['nama' => 'Kementerian Uji']);
    $dokumen = Dokumen::factory()->terbit()->create([
        'jenis_dokumen_id' => $jenis->id,
        'badan_penetap_id' => $badan->id,
        'sebutan_singkat' => 'Permen 1/2025',
        'tanggal_penetapan' => '2025-06-16',
        'judul' => 'Uji </script><script>alert(1)</script>',
    ]);

    $html = (string) $this->get(urlKanonik($dokumen))->assertOk()->getContent();
    $data = jsonLd($html);

    expect($data)->toMatchArray([
        '@context' => 'https://schema.org',
        '@type' => 'Legislation',
        'legislationIdentifier' => 'Permen 1/2025',
        'legislationDate' => '2025-06-16',
        'legislationPassedBy' => ['@type' => 'Organization', 'name' => 'Kementerian Uji'],
        'legislationLegalForce' => 'https://schema.org/InForce',
        'inLanguage' => 'id',
        'url' => urlKanonik($dokumen),
    ])->and($data['name'])->toContain('</script><script>alert(1)</script>')
        ->and($html)->not->toContain('<script>alert(1)</script>');
});

test('JSON-LD dokumen pendukung bertipe CreativeWork', function () {
    $jenis = JenisDokumen::factory()->create(['kelompok' => KelompokJenisDokumen::Pendukung]);
    $dokumen = Dokumen::factory()->terbit()->create(['jenis_dokumen_id' => $jenis->id]);

    $data = jsonLd((string) $this->get(urlKanonik($dokumen))->getContent());

    expect($data['@type'])->toBe('CreativeWork')
        ->and($data)->not->toHaveKey('legislationIdentifier')
        ->and($data['url'])->toBe(urlKanonik($dokumen));
});
