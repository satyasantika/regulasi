<?php

namespace Database\Seeders;

use App\Actions\Berkas\SimpanTautanBerkas;
use App\Actions\Dokumen\PeriksaKelengkapan;
use App\Actions\Dokumen\SimpanDokumen;
use App\Actions\Dokumen\TerbitkanDokumen;
use App\Actions\Relasi\BuatDokumenRujukan;
use App\Actions\Relasi\TambahRelasiDokumen;
use App\Actions\Relasi\TerapkanRelasiDokumen;
use App\Enums\JenisRelasi;
use App\Enums\JenisRiwayatStatus;
use App\Enums\JenisTautan;
use App\Enums\Peran;
use App\Enums\StatusPublikasi;
use App\Enums\Visibilitas;
use App\Models\BadanPenetap;
use App\Models\Dokumen;
use App\Models\DokumenRelasi;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use App\Models\RiwayatStatusDokumen;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Dokumen contoh, dokumen rujukan, dan relasinya (03-SKEMA §4.5) lewat Action agar aturan bisnis ikut teruji.
 * Tidak ada PDF yang disalin/diunggah: naskah = tautan (`tautan` di data atau tautan-contoh.local.php). Entri tanpa
 * tautan tetap draf dengan peringatan. Idempoten: entri yang sudah ada (jenis + badan + nomor + tahun; tanpa nomor:
 * + judul) dan relasi yang sudah ada dilewati.
 *
 * REGULASI_SEED_TERBITKAN_SEMUA=true (hanya lingkungan lokal) menerbitkan semua dokumen contoh untuk demo, melewati BR-05.
 */
class DokumenContohSeeder extends Seeder
{
    public const string CATATAN_DEMO = 'seed demo, melewati BR-05';

    /** @var array<string, Dokumen> */
    private array $dokumen = [];

    private User $pelaku;

    public function run(): void
    {
        $pelaku = User::role(Peran::SuperAdmin->value)->orderBy('created_at')->first();

        if (! $pelaku instanceof User) {
            $this->peringatan('Super-admin belum ada (SEED_SUPERADMIN_*): dokumen contoh dilewati.');

            return;
        }

        $this->pelaku = $pelaku;

        /** @var array{dokumen: list<array<string, mixed>>, rujukan: list<array<string, mixed>>, relasi: list<array{0: string, 1: string, 2: string, 3: string|null}>} $data */
        $data = require database_path('seeders/data/dokumen-contoh.php');
        $tautanLokal = $this->tautanLokal();

        foreach ($data['rujukan'] as $entri) {
            $this->dokumen[(string) $entri['kunci']] = $this->rujukan($entri);
        }

        foreach ($data['dokumen'] as $entri) {
            $kunci = (string) $entri['kunci'];
            $entri['tautan'] = filled($entri['tautan']) ? $entri['tautan'] : ($tautanLokal[$kunci] ?? '');
            $this->dokumen[$kunci] = $this->dokumenContoh($entri);
        }

        foreach ($data['relasi'] as [$sumber, $jenis, $target, $keterangan]) {
            $this->relasi($sumber, JenisRelasi::from($jenis), $target, $keterangan);
        }

        if (config('regulasi.seed_terbitkan_semua') && app()->isLocal()) {
            $this->terbitkanSemua();
        }
    }

    /**
     * @param  array<string, mixed>  $entri
     */
    private function rujukan(array $entri): Dokumen
    {
        return $this->yangSudahAda($entri) ?? app(BuatDokumenRujukan::class)->handle($this->isian($entri), $this->pelaku);
    }

    /**
     * @param  array<string, mixed>  $entri
     */
    private function dokumenContoh(array $entri): Dokumen
    {
        $ada = $this->yangSudahAda($entri);

        if ($ada !== null) {
            return $ada;
        }

        $dokumen = app(SimpanDokumen::class)->handle($this->isian($entri), $this->pelaku);

        $this->tautan($dokumen, (string) $entri['tautan'], JenisTautan::NaskahUtama, 'Naskah');
        $this->tautan($dokumen, (string) $entri['tautan_sumber'], JenisTautan::HalamanSumber, 'Halaman sumber resmi');

        if (blank($entri['tautan'])) {
            $this->peringatan("Tautan naskah belum diisi: {$this->nama($dokumen)} ({$entri['kunci']}).");
        }

        if ($entri['terbit']) {
            $dokumen->load(['jenisDokumen', 'kategoriSistem', 'naskahUtama']);
            $kekurangan = app(PeriksaKelengkapan::class)->handle($dokumen);

            if ($kekurangan === []) {
                app(TerbitkanDokumen::class)->handle($dokumen, $this->pelaku, 'Dokumen contoh (seeder).');
            } else {
                $this->peringatan("Tetap draf: {$this->nama($dokumen)} — ".implode(' ', $kekurangan));
            }
        }

        return $dokumen->refresh();
    }

    private function tautan(Dokumen $dokumen, string $url, JenisTautan $jenis, string $label): void
    {
        if (blank($url)) {
            return;
        }

        try {
            app(SimpanTautanBerkas::class)->handle($dokumen, ['url' => $url, 'jenis' => $jenis, 'label' => $label], $this->pelaku);
        } catch (ValidationException $e) {
            $this->peringatan("Tautan {$dokumen->sebutan_singkat} ditolak: ".implode(' ', Arr::flatten($e->errors())));
        }
    }

    private function relasi(string $kunciSumber, JenisRelasi $jenis, string $kunciTarget, ?string $keterangan): void
    {
        $sumber = $this->dokumen[$kunciSumber] ?? throw new RuntimeException("Dokumen contoh [{$kunciSumber}] tidak ada.");
        $target = $this->dokumen[$kunciTarget] ?? throw new RuntimeException("Dokumen contoh [{$kunciTarget}] tidak ada.");

        $ada = DokumenRelasi::query()
            ->where('dokumen_id', $sumber->getKey())
            ->where('dokumen_terkait_id', $target->getKey())
            ->where('jenis_relasi', $jenis->value)
            ->exists();

        if (! $ada) {
            app(TambahRelasiDokumen::class)->handle($sumber, $target, $jenis, $keterangan, $this->pelaku);
        }
    }

    /**
     * Demo lokal: terbitkan langsung semua dokumen contoh yang belum terbit (melewati BR-05) lalu terapkan relasinya.
     */
    private function terbitkanSemua(): void
    {
        foreach ($this->dokumen as $dokumen) {
            DB::transaction(function () use ($dokumen): void {
                /** @var Dokumen $baris */
                $baris = Dokumen::query()->whereKey($dokumen->getKey())->lockForUpdate()->firstOrFail();

                if ($baris->status_publikasi === StatusPublikasi::Terbit) {
                    return;
                }

                $asal = $baris->status_publikasi;
                $baris->status_publikasi = StatusPublikasi::Terbit;
                $baris->diverifikasi_oleh = $this->pelaku->getKey();
                $baris->diverifikasi_pada = now();
                $baris->dipublikasikan_pada ??= now();
                $baris->save();

                RiwayatStatusDokumen::query()->create([
                    'dokumen_id' => $baris->getKey(),
                    'jenis' => JenisRiwayatStatus::Publikasi,
                    'dari' => $asal->value,
                    'ke' => StatusPublikasi::Terbit->value,
                    'catatan' => self::CATATAN_DEMO,
                    'oleh' => $this->pelaku->getKey(),
                ]);
            });
        }

        foreach ($this->dokumen as $dokumen) {
            app(TerapkanRelasiDokumen::class)->handle($dokumen->refresh(), $this->pelaku);
        }

        $this->peringatan('REGULASI_SEED_TERBITKAN_SEMUA: semua dokumen contoh diterbitkan ('.self::CATATAN_DEMO.').');
    }

    /**
     * @param  array<string, mixed>  $entri
     * @return array<string, mixed>
     */
    private function isian(array $entri): array
    {
        return [
            'jenis_dokumen_id' => $this->jenisId((string) $entri['jenis']),
            'badan_penetap_id' => $this->badanId((string) $entri['badan']),
            'nomor' => $entri['nomor'],
            'tahun' => $entri['tahun'],
            'judul' => $entri['judul'],
            'tanggal_penetapan' => $entri['tanggal_penetapan'],
            'tanggal_berlaku' => $entri['tanggal_berlaku'],
            'sumber' => $entri['sumber'],
            'catatan' => $entri['keterangan'],
            'visibilitas' => Visibilitas::Publik->value,
            'kategori_sistem' => array_map(fn (string $kode): string => $this->kategoriId($kode), (array) $entri['kategori']),
            'tag' => array_map(fn (string $nama): string => $this->tagId($nama), (array) $entri['tag']),
        ];
    }

    /**
     * @param  array<string, mixed>  $entri
     */
    private function yangSudahAda(array $entri): ?Dokumen
    {
        return Dokumen::withTrashed()
            ->where('jenis_dokumen_id', $this->jenisId((string) $entri['jenis']))
            ->where('badan_penetap_id', $this->badanId((string) $entri['badan']))
            ->where('tahun', $entri['tahun'])
            ->when(
                filled($entri['nomor']),
                fn ($q) => $q->where('nomor', $entri['nomor']),
                fn ($q) => $q->whereNull('nomor')->where('judul', $entri['judul']),
            )
            ->first();
    }

    private function jenisId(string $kode): string
    {
        return JenisDokumen::withTrashed()->where('kode', $kode)->value('id')
            ?? throw new RuntimeException("Jenis dokumen [{$kode}] belum di-seed.");
    }

    private function badanId(string $badan): string
    {
        return BadanPenetap::withTrashed()->where('singkatan', $badan)->orWhere('nama', $badan)->value('id')
            ?? throw new RuntimeException("Badan penetap [{$badan}] belum di-seed.");
    }

    private function kategoriId(string $kode): string
    {
        return KategoriSistem::withTrashed()->where('kode', $kode)->value('id')
            ?? throw new RuntimeException("Kategori sistem [{$kode}] belum di-seed.");
    }

    private function tagId(string $nama): string
    {
        return Tag::query()->firstOrCreate(['nama' => $nama])->getKey();
    }

    /**
     * URL naskah lokal (di-.gitignore) yang mengisi `tautan` kosong: array kunci => URL.
     *
     * @return array<string, string>
     */
    private function tautanLokal(): array
    {
        $berkas = (string) config('regulasi.seed_tautan_lokal');

        if ($berkas === '' || ! is_file($berkas)) {
            return [];
        }

        $isi = require $berkas;

        return is_array($isi) ? array_filter($isi, fn (mixed $url): bool => is_string($url) && $url !== '') : [];
    }

    /**
     * Sebutan untuk pesan; dokumen tanpa nomor (pedoman/instrumen) ditambah judulnya agar dapat dibedakan.
     */
    private function nama(Dokumen $dokumen): string
    {
        return blank($dokumen->nomor) ? $dokumen->sebutan_singkat.' "'.Str::limit($dokumen->judul, 70).'"' : $dokumen->sebutan_singkat;
    }

    private function peringatan(string $pesan): void
    {
        /** @var Command|null $perintah Kosong bila seeder dijalankan tanpa perintah artisan. */
        $perintah = $this->command;
        $perintah?->warn($pesan);

        logger()->info('[DokumenContohSeeder] '.$pesan);
    }
}
