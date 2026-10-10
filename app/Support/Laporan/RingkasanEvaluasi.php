<?php

namespace App\Support\Laporan;

use App\Enums\JenisRiwayatStatus;
use App\Enums\StatusBerlaku;
use App\Enums\StatusCekTautan;
use App\Enums\StatusPublikasi;
use App\Enums\Visibilitas;
use App\Models\Dokumen;
use App\Models\KlienApi;
use App\Models\RiwayatStatusDokumen;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Snapshot angka laporan evaluasi periode (F11.4, PRD §8, US-LAP-03): koleksi, penambahan periode, perubahan
 * status, kelengkapan, kesehatan tautan, pembukaan tautan, dan penggunaan API. Disimpan sebagai JSON di
 * laporan_evaluasi.ringkasan sehingga PDF laporan final tidak berubah walau data berubah.
 */
final class RingkasanEvaluasi
{
    public const int JUMLAH_TERPOPULER = 10;

    public const int MAKS_DAFTAR_PERUBAHAN = 100;

    /**
     * @return array<string, mixed>
     */
    public static function hitung(CarbonImmutable $mulai, CarbonImmutable $selesai): array
    {
        $mulai = $mulai->startOfDay();
        $selesai = $selesai->endOfDay();
        $lingkup = LingkupLaporan::global();
        $saringan = ['dari' => $mulai, 'sampai' => $selesai->startOfDay(), 'saluran' => null, 'kategori' => null];

        return [
            'dihitung_pada' => now()->toIso8601String(),
            'periode' => ['mulai' => $mulai->toDateString(), 'selesai' => $selesai->toDateString()],
            'koleksi' => self::koleksi(),
            'penambahan' => self::penambahan($mulai, $selesai),
            'perubahan_status' => self::perubahanStatus($mulai, $selesai),
            'kelengkapan' => self::kelengkapan(),
            'kesehatan_tautan' => self::kesehatanTautan(),
            'pembukaan' => [
                ...PembukaanTautan::total($lingkup, $saringan),
                'terpopuler' => PembukaanTautan::perDokumen($lingkup, $saringan)
                    ->orderByDesc('dibuka_total')
                    ->orderBy('dokumen.sebutan_singkat')
                    ->limit(self::JUMLAH_TERPOPULER)
                    ->get()
                    ->map(fn (Dokumen $d): array => [
                        'sebutan' => $d->sebutan_singkat,
                        'judul' => $d->judul,
                        'web' => (int) $d->getAttribute('dibuka_web'),
                        'api' => (int) $d->getAttribute('dibuka_api'),
                        'total' => (int) $d->getAttribute('dibuka_total'),
                    ])
                    ->values()
                    ->all(),
            ],
            'penggunaan_api' => PenggunaanApi::query($mulai, $selesai)
                ->orderByDesc('dibuka_api')
                ->orderBy('klien_api.nama')
                ->get()
                ->map(fn (KlienApi $k): array => [
                    'nama' => $k->nama,
                    'aktif' => $k->aktif && ! $k->trashed(),
                    'dibuka_api' => (int) $k->getAttribute('dibuka_api'),
                    'terakhir_dipakai_pada' => $k->terakhir_dipakai_pada?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function koleksi(): array
    {
        $terbit = fn () => Dokumen::query()->terbit()->toBase();

        $perStatus = $terbit()->groupBy('status_berlaku')->selectRaw('status_berlaku, COUNT(*) AS jumlah')->pluck('jumlah', 'status_berlaku');

        return [
            'total_terbit' => $terbit()->count(),
            'publik' => $terbit()->where('visibilitas', Visibilitas::Publik->value)->count(),
            'internal' => $terbit()->where('visibilitas', Visibilitas::Internal->value)->count(),
            'per_jenis' => self::perJenis($terbit()),
            'per_status' => collect(StatusBerlaku::cases())
                ->map(fn (StatusBerlaku $s): array => ['status' => $s->label(), 'jumlah' => (int) ($perStatus[$s->value] ?? 0)])
                ->values()
                ->all(),
        ];
    }

    /**
     * Dokumen yang pertama kali dipublikasikan dalam periode (apa pun status publikasinya kini).
     *
     * @return array<string, mixed>
     */
    private static function penambahan(CarbonImmutable $mulai, CarbonImmutable $selesai): array
    {
        $query = fn () => Dokumen::query()->whereBetween('dokumen.dipublikasikan_pada', [$mulai, $selesai])->toBase();

        return [
            'jumlah' => $query()->count(),
            'per_jenis' => self::perJenis($query()),
        ];
    }

    /**
     * Dokumen yang menjadi dicabut/diubah dalam periode (dari riwayat status berlaku).
     *
     * @return array<string, mixed>
     */
    private static function perubahanStatus(CarbonImmutable $mulai, CarbonImmutable $selesai): array
    {
        $query = fn () => RiwayatStatusDokumen::query()
            ->where('jenis', JenisRiwayatStatus::Berlaku->value)
            ->whereIn('ke', [StatusBerlaku::Dicabut->value, StatusBerlaku::Diubah->value])
            ->whereBetween('created_at', [$mulai, $selesai]);

        $jumlah = $query()->toBase()->groupBy('ke')->selectRaw('ke, COUNT(DISTINCT dokumen_id) AS jumlah')->pluck('jumlah', 'ke');

        return [
            'dicabut' => (int) ($jumlah[StatusBerlaku::Dicabut->value] ?? 0),
            'diubah' => (int) ($jumlah[StatusBerlaku::Diubah->value] ?? 0),
            'daftar' => $query()
                ->with('dokumen')
                ->orderBy('created_at')
                ->limit(self::MAKS_DAFTAR_PERUBAHAN)
                ->get()
                ->map(fn (RiwayatStatusDokumen $r): array => [
                    'sebutan' => $r->dokumen->sebutan_singkat,
                    'judul' => $r->dokumen->judul,
                    'ke' => StatusBerlaku::tryFrom($r->ke)?->label() ?? $r->ke,
                    'tanggal' => $r->created_at->toDateString(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{total: int, persen_abstrak: int|null, persen_naskah_utama: int|null, persen_teks: int|null}
     */
    private static function kelengkapan(): array
    {
        $baris = Dokumen::query()->terbit()->toBase()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN '.KriteriaKelengkapan::sql('abstrak').' THEN 1 ELSE 0 END) AS abstrak')
            ->selectRaw('SUM(CASE WHEN '.KriteriaKelengkapan::sql('naskah_utama').' THEN 1 ELSE 0 END) AS naskah_utama')
            ->selectRaw('SUM(CASE WHEN '.KriteriaKelengkapan::sql('teks').' THEN 1 ELSE 0 END) AS teks')
            ->first();
        $total = (int) ($baris->total ?? 0);
        $persen = fn (string $kolom): ?int => $total === 0 ? null : (int) round(((int) ($baris->{$kolom} ?? 0)) * 100 / $total);

        return [
            'total' => $total,
            'persen_abstrak' => $persen('abstrak'),
            'persen_naskah_utama' => $persen('naskah_utama'),
            'persen_teks' => $persen('teks'),
        ];
    }

    /**
     * Tautan berkas aktif milik dokumen terbit menurut hasil pemeriksaan terakhir.
     *
     * @return array{total: int, dapat_diakses: int, tidak_dapat_diakses: int, belum: int, persen_dapat_diakses: int|null}
     */
    private static function kesehatanTautan(): array
    {
        $jumlah = DB::table('tautan_berkas')
            ->join('dokumen', 'dokumen.id', '=', 'tautan_berkas.pemilik_id')
            ->where('tautan_berkas.pemilik_type', 'dokumen')
            ->whereNull('tautan_berkas.deleted_at')
            ->whereNull('dokumen.deleted_at')
            ->where('dokumen.status_publikasi', StatusPublikasi::Terbit->value)
            ->groupBy('tautan_berkas.status_cek')
            ->selectRaw('tautan_berkas.status_cek AS status, COUNT(*) AS jumlah')
            ->pluck('jumlah', 'status');
        $dapat = (int) ($jumlah[StatusCekTautan::DapatDiakses->value] ?? 0);
        $tidak = (int) ($jumlah[StatusCekTautan::TidakDapatDiakses->value] ?? 0);
        $belum = (int) ($jumlah[StatusCekTautan::Belum->value] ?? 0);
        $total = $dapat + $tidak + $belum;

        return [
            'total' => $total,
            'dapat_diakses' => $dapat,
            'tidak_dapat_diakses' => $tidak,
            'belum' => $belum,
            'persen_dapat_diakses' => $total === 0 ? null : (int) round($dapat * 100 / $total),
        ];
    }

    /**
     * @return list<array{nama: string, jumlah: int}>
     */
    private static function perJenis(Builder $query): array
    {
        return $query
            ->join('jenis_dokumen', 'jenis_dokumen.id', '=', 'dokumen.jenis_dokumen_id')
            ->groupBy('jenis_dokumen.id', 'jenis_dokumen.nama', 'jenis_dokumen.urutan')
            ->orderBy('jenis_dokumen.urutan')
            ->orderBy('jenis_dokumen.nama')
            ->selectRaw('jenis_dokumen.nama AS nama, COUNT(*) AS jumlah')
            ->get()
            ->map(fn (object $b): array => ['nama' => (string) $b->nama, 'jumlah' => (int) $b->jumlah])
            ->values()
            ->all();
    }
}
