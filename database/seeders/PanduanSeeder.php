<?php

namespace Database\Seeders;

use App\Actions\Api\BuatTokenKlienApi;
use App\Actions\Berkas\SimpanTautanBerkas;
use App\Actions\Dokumen\AjukanVerifikasi;
use App\Actions\Dokumen\SimpanDokumen;
use App\Actions\Dokumen\TerbitkanDokumen;
use App\Actions\Dokumen\TolakDokumen;
use App\Enums\JenisTautan;
use App\Enums\Peran;
use App\Enums\StatusCekTautan;
use App\Enums\Visibilitas;
use App\Jobs\EkstrakTeksTautan;
use App\Jobs\PeriksaTautanBerkas;
use App\Models\BadanPenetap;
use App\Models\Dokumen;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use App\Models\KlienApi;
use App\Models\LaporanEvaluasi;
use App\Models\Tag;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Support\Laporan\RingkasanEvaluasi;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Bus;
use RuntimeException;

/**
 * Akun demo per peran + data rekaan untuk tangkapan layar panduan (F12.6). HANYA APP_ENV=local.
 *
 * - Akun panduan.<peran>@contoh.test, kata sandi PANDUAN_PASSWORD; MFA tidak diwajibkan untuk akun ini di
 *   lingkungan local (User::wajibMfa). Tidak memuat data pribadi asli.
 * - Master + dokumen contoh (metadata peraturan publik) diterbitkan untuk demo, ditambah dokumen rekaan berawalan
 *   "(Contoh Panduan)" pada berbagai status: draf bertautan, menunggu verifikasi, ditolak, dan internal terbit.
 * - Tautan memakai ID Drive rekaan; pemeriksaan/ekstraksi tidak diantrekan (tanpa jaringan) dan status cek diisi
 *   "dapat diakses" agar layar tidak menampilkan galat palsu.
 *
 * Pakai pada basis data lokal: php artisan migrate:fresh --seeder=PanduanSeeder
 */
class PanduanSeeder extends Seeder
{
    public const string AWALAN = '(Contoh Panduan)';

    /**
     * Akun demo: peran => nama tampilan.
     *
     * @var array<string, string>
     */
    public const array AKUN = [
        'super-admin' => 'Admin TI (Contoh)',
        'admin-regulasi' => 'Admin Regulasi (Contoh)',
        'kontributor' => 'Kontributor Aset (Contoh)',
        'pengguna' => 'Pengguna Kampus (Contoh)',
    ];

    public function run(): void
    {
        if (! app()->isLocal()) {
            throw new RuntimeException('PanduanSeeder hanya boleh dijalankan di APP_ENV=local.');
        }

        if (blank(config('regulasi.panduan.password'))) {
            throw new RuntimeException('PANDUAN_PASSWORD wajib diisi di .env lokal.');
        }

        // Tanpa jaringan: pemeriksaan & ekstraksi tautan rekaan tidak dijalankan.
        Bus::fake([PeriksaTautanBerkas::class, EkstrakTeksTautan::class]);

        $this->call([PeranDanIzinSeeder::class, JenisDokumenSeeder::class, BadanPenetapSeeder::class, KategoriSistemSeeder::class, TagSeeder::class]);

        $akun = [];
        foreach (self::AKUN as $peran => $nama) {
            $akun[$peran] = $this->akun($peran, $nama);
        }

        $akun['kontributor']->kategoriSistem()->syncWithoutDetaching(KategoriSistem::query()->whereIn('kode', ['aset', 'persuratan'])->pluck('id')->all());

        // Dokumen contoh (super-admin panduan = pelaku seeder) diterbitkan untuk demo lokal.
        config(['regulasi.seed_terbitkan_semua' => true]);
        $this->call(DokumenContohSeeder::class);

        $this->dokumenRekaan($akun);
        $this->klienApi($akun['super-admin']);
        $this->laporan($akun['admin-regulasi']);

        TautanBerkas::query()->where('status_cek', StatusCekTautan::Belum->value)
            ->update(['status_cek' => StatusCekTautan::DapatDiakses->value, 'dicek_pada' => now()]);
    }

    public static function surel(string $peran): string
    {
        return "panduan.{$peran}@".User::DOMAIN_PANDUAN;
    }

    private function akun(string $peran, string $nama): User
    {
        $pengguna = User::query()->firstOrNew(['email' => self::surel($peran)]);
        $pengguna->forceFill([
            'name' => $nama,
            'password' => (string) config('regulasi.panduan.password'),
            'aktif' => true,
            'email_verified_at' => $pengguna->email_verified_at ?? now(),
        ])->save();
        $pengguna->syncRoles([$peran]);

        return $pengguna;
    }

    /**
     * @param  array<string, User>  $akun
     */
    private function dokumenRekaan(array $akun): void
    {
        $kontributor = $akun['kontributor'];
        $admin = $akun['admin-regulasi'];

        $draf = $this->dokumen($kontributor, 'SOP', null, 'Peminjaman Barang Milik Negara di Lingkungan FKIP '.self::AWALAN, 'aset', [
            'abstrak' => 'Mengatur tata cara peminjaman barang milik negara oleh dosen, tenaga kependidikan, dan organisasi mahasiswa: pengajuan, persetujuan, serah terima, dan pengembalian.',
        ]);
        $this->tautan($draf, $kontributor, '1ContohPanduanSopPeminjamanBMN01', JenisTautan::NaskahUtama, 'Naskah SOP');
        $this->tautan($draf, $kontributor, '1ContohPanduanFormulirPinjam0002', JenisTautan::Lampiran, 'Lampiran — Formulir peminjaman');

        $menunggu = $this->dokumen($kontributor, 'PEDOMAN', null, 'Inventarisasi Aset Laboratorium FKIP '.self::AWALAN, 'aset', [
            'abstrak' => 'Pedoman pencatatan, pelabelan, dan pemutakhiran data aset laboratorium setiap semester.',
        ]);
        $this->tautan($menunggu, $kontributor, '1ContohPanduanPedomanInventaris03', JenisTautan::NaskahUtama, 'Naskah pedoman');
        app(AjukanVerifikasi::class)->handle($menunggu->refresh(), $kontributor);

        $ditolak = $this->dokumen($kontributor, 'JUKNIS', null, 'Penghapusan Barang Milik Negara Rusak Berat '.self::AWALAN, 'aset');
        $this->tautan($ditolak, $kontributor, '1ContohPanduanJuknisPenghapusan04', JenisTautan::NaskahUtama, 'Naskah juknis');
        app(AjukanVerifikasi::class)->handle($ditolak->refresh(), $kontributor);
        app(TolakDokumen::class)->handle($ditolak->refresh(), $admin, 'Lengkapi abstrak dan pastikan judul sesuai halaman sampul naskah.');

        $internal = $this->dokumen($admin, 'SE', '01/UN58.07/SE/2026', 'Penggunaan Ruang Rapat FKIP '.self::AWALAN, 'persuratan', [
            'visibilitas' => Visibilitas::Internal->value,
            'tanggal_penetapan' => '2026-08-03',
            'tempat_penetapan' => 'Tasikmalaya',
            'abstrak' => 'Surat edaran internal tentang pemesanan dan tata tertib penggunaan ruang rapat fakultas.',
        ], 'fkip-unsil');
        $this->tautan($internal, $admin, '1ContohPanduanSuratEdaranRapat05', JenisTautan::NaskahUtama, 'Naskah surat edaran');
        app(TerbitkanDokumen::class)->handle($internal->refresh(), $admin, 'Contoh panduan.');
    }

    /**
     * @param  array<string, mixed>  $tambahan
     */
    private function dokumen(User $oleh, string $kodeJenis, ?string $nomor, string $judul, string $kodeKategori, array $tambahan = [], string $badan = 'fkip-unsil'): Dokumen
    {
        $jenisId = JenisDokumen::query()->where('kode', $kodeJenis)->value('id');
        $ada = Dokumen::query()->where('judul', $judul)->first();

        if ($ada !== null) {
            return $ada;
        }

        return app(SimpanDokumen::class)->handle([
            'jenis_dokumen_id' => $jenisId,
            'badan_penetap_id' => BadanPenetap::query()->where('slug', $badan)->value('id'),
            'nomor' => $nomor,
            'tahun' => 2026,
            'judul' => $judul,
            'visibilitas' => Visibilitas::Publik->value,
            'kategori_sistem' => [KategoriSistem::query()->where('kode', $kodeKategori)->value('id')],
            'tag' => Tag::query()->where('slug', 'barang-milik-negara')->pluck('id')->all(),
            ...$tambahan,
        ], $oleh);
    }

    private function tautan(Dokumen $dokumen, User $oleh, string $idDrive, JenisTautan $jenis, string $label): void
    {
        $url = "https://drive.google.com/file/d/{$idDrive}/view";

        if (! TautanBerkas::query()->where('url', $url)->exists()) {
            app(SimpanTautanBerkas::class)->handle($dokumen, ['url' => $url, 'jenis' => $jenis, 'label' => $label], $oleh);
        }
    }

    private function klienApi(User $superAdmin): void
    {
        $klien = KlienApi::query()->firstOrCreate(['nama' => 'Sistem Aset FKIP '.self::AWALAN], [
            'kategori_sistem_id' => KategoriSistem::query()->where('kode', 'aset')->value('id'),
            'kontak_nama' => 'Pengelola Sistem Aset (Contoh)',
            'kontak_email' => 'aset@'.User::DOMAIN_PANDUAN,
            'aktif' => true,
        ]);

        if (! $klien->tokens()->exists()) {
            app(BuatTokenKlienApi::class)->handle($klien, 'Token produksi Sistem Aset', false, 90, $superAdmin);
        }
    }

    private function laporan(User $admin): void
    {
        $judul = 'Laporan Evaluasi Semester Genap 2025/2026 '.self::AWALAN;

        if (LaporanEvaluasi::query()->where('judul', $judul)->exists()) {
            return;
        }

        $laporan = new LaporanEvaluasi([
            'judul' => $judul,
            'periode_mulai' => '2026-02-01',
            'periode_selesai' => '2026-07-31',
            'analisis' => 'Koleksi dokumen akreditasi bertambah; kelengkapan abstrak perlu ditingkatkan.',
            'tindak_lanjut' => 'Kontributor melengkapi abstrak dokumen terbit tanpa abstrak.',
            'dibuat_oleh' => $admin->id,
        ]);
        $laporan->forceFill(['ringkasan' => RingkasanEvaluasi::hitung(CarbonImmutable::parse('2026-02-01'), CarbonImmutable::parse('2026-07-31'))])->save();
    }
}
