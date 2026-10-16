<?php

use App\Actions\Dokumen\AjukanVerifikasi;
use App\Actions\Dokumen\TerbitkanDokumen;
use App\Actions\Dokumen\TolakDokumen;
use App\Actions\Relasi\TambahRelasiDokumen;
use App\Enums\JenisRelasi;
use App\Enums\JenisTautan;
use App\Enums\KelompokJenisDokumen;
use App\Enums\PenyediaTautan;
use App\Enums\Peran;
use App\Enums\StatusCekTautan;
use App\Enums\StatusPublikasi;
use App\Events\IsiBerkasBerubah as PeristiwaIsiBerkasBerubah;
use App\Jobs\EkstrakTeksTautan;
use App\Jobs\PeriksaTautanBerkas;
use App\Models\Dokumen;
use App\Models\EkstraksiTeks;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Notifications\DokumenDiajukan;
use App\Notifications\DokumenDiterbitkan;
use App\Notifications\DokumenDitolak;
use App\Notifications\EkstraksiGagal;
use App\Notifications\IsiBerkasBerubah;
use App\Notifications\StatusDokumenBerubah;
use App\Notifications\TautanTidakDapatDiakses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
});

/**
 * Dokumen peraturan yang lolos kelengkapan BR-05, dibuat oleh $pembuat di $kategori.
 */
function dokumenUntukNotifikasi(User $pembuat, KategoriSistem $kategori, StatusPublikasi $status = StatusPublikasi::Draf): Dokumen
{
    $dokumen = Dokumen::factory()->berstatus($status)->create([
        'jenis_dokumen_id' => JenisDokumen::factory()->create(['kelompok' => KelompokJenisDokumen::Peraturan]),
        'tanggal_penetapan' => '2025-01-15',
        'dibuat_oleh' => $pembuat->getKey(),
    ]);
    $dokumen->kategoriSistem()->attach($kategori);

    TautanBerkas::query()->create([
        'pemilik_type' => 'dokumen',
        'pemilik_id' => $dokumen->getKey(),
        'jenis' => JenisTautan::NaskahUtama,
        'url' => 'https://drive.google.com/file/d/1NotifAbCdEfGhIjKlMn'.fake()->unique()->numberBetween(10000, 99999).'/view',
        'penyedia' => PenyediaTautan::GoogleDrive,
        'ditambahkan_oleh' => $pembuat->getKey(),
    ]);

    return $dokumen;
}

function kontributorDi(KategoriSistem $kategori, array $atribut = []): User
{
    $kontributor = penggunaDenganPeran(Peran::Kontributor, $atribut);
    $kontributor->kategoriSistem()->attach($kategori);

    return $kontributor;
}

test('ajukan memberi tahu admin-regulasi lewat database dan surel di antrean notifikasi; super-admin tidak', function () {
    $kategori = KategoriSistem::factory()->create();
    $kontributor = kontributorDi($kategori);
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $superAdmin = penggunaDenganPeran(Peran::SuperAdmin);
    $dokumen = dokumenUntukNotifikasi($kontributor, $kategori);

    app(AjukanVerifikasi::class)->handle($dokumen, $kontributor);

    Notification::assertSentTo($admin, DokumenDiajukan::class, function (DokumenDiajukan $n, array $kanal) use ($admin, $dokumen): bool {
        $basisData = $n->toDatabase($admin);

        return $kanal === ['database', 'mail']
            && $n->queue === 'default'
            && $n->afterCommit === true
            && $n->viaQueues() === ['database' => 'default', 'mail' => 'default']
            && $n->dokumenId === $dokumen->getKey()
            && $basisData['title'] === "Dokumen menunggu verifikasi: {$dokumen->sebutan_singkat}"
            && $basisData['format'] === 'filament'
            && str_contains((string) $basisData['actions'][0]['url'], "/admin/dokumen/{$dokumen->getKey()}");
    });
    Notification::assertNotSentTo([$superAdmin, $kontributor], DokumenDiajukan::class);
});

test('tanpa admin-regulasi aktif, pengajuan diteruskan ke super-admin', function () {
    $kategori = KategoriSistem::factory()->create();
    $kontributor = kontributorDi($kategori);
    $superAdmin = penggunaDenganPeran(Peran::SuperAdmin);
    penggunaDenganPeran(Peran::AdminRegulasi, ['aktif' => false]);

    app(AjukanVerifikasi::class)->handle(dokumenUntukNotifikasi($kontributor, $kategori), $kontributor);

    Notification::assertSentTo($superAdmin, DokumenDiajukan::class);
});

test('tolak memberi tahu pembuat draf dengan catatan; terbit memberi tautan publik', function () {
    $kategori = KategoriSistem::factory()->create();
    $kontributor = kontributorDi($kategori);
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $dokumen = dokumenUntukNotifikasi($kontributor, $kategori, StatusPublikasi::MenungguVerifikasi);

    app(TolakDokumen::class)->handle($dokumen, $admin, 'Nomor dokumen tidak sesuai naskah.');

    Notification::assertSentTo($kontributor, DokumenDitolak::class, function (DokumenDitolak $n) use ($kontributor): bool {
        $surel = $n->toMail($kontributor);

        return $n->catatan === 'Nomor dokumen tidak sesuai naskah.'
            && in_array('Catatan: Nomor dokumen tidak sesuai naskah.', $surel->introLines, true)
            && str_contains((string) $n->toDatabase($kontributor)['body'], 'Nomor dokumen tidak sesuai naskah.');
    });
    Notification::assertNotSentTo($admin, DokumenDitolak::class);

    app(AjukanVerifikasi::class)->handle($dokumen->refresh(), $kontributor);
    app(TerbitkanDokumen::class)->handle($dokumen->refresh(), $admin);

    Notification::assertSentTo($kontributor, DokumenDiterbitkan::class, fn (DokumenDiterbitkan $n): bool => $n->url() === route('dokumen.show', ['dokumen' => $dokumen->getKey(), 'slug' => $dokumen->slug]));
});

test('admin yang menerbitkan draf miliknya sendiri tidak memberi tahu dirinya', function () {
    $kategori = KategoriSistem::factory()->create();
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);

    app(TerbitkanDokumen::class)->handle(dokumenUntukNotifikasi($admin, $kategori), $admin);

    Notification::assertNothingSent();
});

test('relasi mencabut di kategori persuratan memberi tahu kontributor persuratan, bukan kontributor aset', function () {
    $persuratan = KategoriSistem::factory()->create(['nama' => 'Persuratan']);
    $aset = KategoriSistem::factory()->create(['nama' => 'Aset/BMN']);
    $kontributorSurat = kontributorDi($persuratan);
    $kontributorAset = kontributorDi($aset);
    $adminLain = penggunaDenganPeran(Peran::AdminRegulasi);
    $pelaku = penggunaDenganPeran(Peran::AdminRegulasi);

    $lama = Dokumen::factory()->terbit()->create(['sebutan_singkat' => 'Permendikbud 3/2021', 'tanggal_penetapan' => '2021-01-01']);
    $lama->kategoriSistem()->attach($persuratan);
    $baru = Dokumen::factory()->terbit()->create(['sebutan_singkat' => 'Permendiktisaintek 42/2025', 'tanggal_penetapan' => '2025-06-01']);
    $baru->kategoriSistem()->attach($aset);

    app(TambahRelasiDokumen::class)->handle($baru, $lama, JenisRelasi::Mencabut, null, $pelaku);

    Notification::assertSentTo([$kontributorSurat, $adminLain], StatusDokumenBerubah::class, fn (StatusDokumenBerubah $n): bool => str_starts_with($n->isi(), 'Permendikbud 3/2021 kini Dicabut oleh Permendiktisaintek 42/2025.')
        && str_contains($n->isi(), 'Berlaku → Dicabut'));
    Notification::assertNotSentTo([$kontributorAset, $pelaku], StatusDokumenBerubah::class);
});

test('pengguna nonaktif tidak menerima notifikasi dan via() kosong untuk penerima nonaktif', function () {
    $kategori = KategoriSistem::factory()->create();
    $kontributor = kontributorDi($kategori);
    $adminNonaktif = penggunaDenganPeran(Peran::AdminRegulasi, ['aktif' => false]);
    $adminAktif = penggunaDenganPeran(Peran::AdminRegulasi);
    $dokumen = dokumenUntukNotifikasi($kontributor, $kategori);

    app(AjukanVerifikasi::class)->handle($dokumen, $kontributor);

    Notification::assertSentTo($adminAktif, DokumenDiajukan::class);
    Notification::assertNotSentTo($adminNonaktif, DokumenDiajukan::class);
    expect((new DokumenDiajukan($dokumen, $kontributor))->via($adminNonaktif))->toBe([]);
});

test('transaksi yang gagal tidak mengirim notifikasi', function () {
    $kategori = KategoriSistem::factory()->create();
    $kontributor = kontributorDi($kategori);
    penggunaDenganPeran(Peran::AdminRegulasi);
    $dokumen = dokumenUntukNotifikasi($kontributor, $kategori);

    expect(fn () => DB::transaction(function () use ($dokumen, $kontributor): void {
        app(AjukanVerifikasi::class)->handle($dokumen, $kontributor);

        throw new RuntimeException('Gagal setelah pengajuan.');
    }))->toThrow(RuntimeException::class);

    expect($dokumen->refresh()->status_publikasi)->toBe(StatusPublikasi::Draf);
    Notification::assertNothingSent();
});

test('tautan dokumen terbit yang menjadi tidak dapat diakses memberi tahu penambah dan admin', function () {
    dnsPalsu();
    Http::fake(['drive.google.com/*' => Http::response('', 404)]);
    $kategori = KategoriSistem::factory()->create();
    $kontributor = kontributorDi($kategori);
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $dokumen = dokumenUntukNotifikasi($kontributor, $kategori, StatusPublikasi::Terbit);
    $tautan = $dokumen->naskahUtama()->firstOrFail();

    $job = (new PeriksaTautanBerkas($tautan))->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    expect($tautan->fresh()->status_cek)->toBe(StatusCekTautan::TidakDapatDiakses);
    Notification::assertSentTo([$kontributor, $admin], TautanTidakDapatDiakses::class, fn (TautanTidakDapatDiakses $n, array $kanal): bool => $kanal === ['database', 'mail']
        && ! str_contains($n->isi(), 'drive.google.com'));
});

test('tautan draf yang tidak dapat diakses hanya memberi tahu penambahnya', function () {
    dnsPalsu();
    Http::fake(['drive.google.com/*' => Http::response('', 404)]);
    $kategori = KategoriSistem::factory()->create();
    $kontributor = kontributorDi($kategori);
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $dokumen = dokumenUntukNotifikasi($kontributor, $kategori);

    $job = (new PeriksaTautanBerkas($dokumen->naskahUtama()->firstOrFail()))->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    Notification::assertSentTo($kontributor, TautanTidakDapatDiakses::class);
    Notification::assertNotSentTo($admin, TautanTidakDapatDiakses::class);
});

test('isi berkas berubah dan ekstraksi gagal memberi tahu admin hanya lewat database', function () {
    $kategori = KategoriSistem::factory()->create();
    $kontributor = kontributorDi($kategori);
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $dokumen = dokumenUntukNotifikasi($kontributor, $kategori, StatusPublikasi::Terbit);
    $tautan = $dokumen->naskahUtama()->firstOrFail();
    $ekstraksi = EkstraksiTeks::query()->create(['tautan_berkas_id' => $tautan->getKey()]);

    PeristiwaIsiBerkasBerubah::dispatch($tautan, $ekstraksi, str_repeat('a', 64));

    Notification::assertSentTo($admin, IsiBerkasBerubah::class, fn (IsiBerkasBerubah $n, array $kanal): bool => $kanal === ['database']);

    (new EkstrakTeksTautan($tautan->getKey()))->failed(new RuntimeException('pdftotext keluar dengan kode 1'));

    Notification::assertSentTo($admin, EkstraksiGagal::class, fn (EkstraksiGagal $n, array $kanal): bool => $kanal === ['database']
        && $n->pesan === 'pdftotext keluar dengan kode 1');
    Notification::assertNotSentTo($kontributor, EkstraksiGagal::class);
});

test('panel admin menampilkan lonceng notifikasi database dengan polling 30 detik', function () {
    $panel = Filament\Facades\Filament::getPanel('admin');

    expect($panel->hasDatabaseNotifications())->toBeTrue()
        ->and($panel->getDatabaseNotificationsPollingInterval())->toBe('30s');
});
