<?php

namespace App\Support\Laporan;

use App\Enums\StatusBerlaku;
use App\Enums\StatusPublikasi;
use App\Models\Dokumen;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Angka agregat dasbor admin (F11.1, US-LAP-01), di-cache 10 menit per peran & himpunan kategori
 * (kunci `dasbor:{peran}:{hash kategori}`). Kunci yang pernah dibuat dicatat di `dasbor:indeks` agar
 * CacheRegulasi::lupakanPublik() dapat membersihkan semuanya saat publikasi/status berubah (BR-20).
 *
 * Pembukaan tautan dihitung dari log_buka_tautan (saluran web/api); pratinjau sematan Drive tidak tercatat
 * di sana sehingga tidak ikut dihitung (BR-17).
 */
final class StatistikDasbor
{
    public const int TTL_DETIK = 600;

    public const int HARI_PEMBUKAAN = 30;

    public const int JUMLAH_TERPOPULER = 10;

    public const int RENTANG_TAHUN = 10;

    public const string KUNCI_INDEKS = 'dasbor:indeks';

    /**
     * @return array{
     *     total_terbit: int,
     *     menunggu_verifikasi: int,
     *     dicabut: int,
     *     dibuka_30_hari: int,
     *     persen_abstrak: int|null,
     *     persen_teks: int|null,
     *     persen_lengkap: int|null,
     *     per_jenis: list<array{nama: string, jumlah: int}>,
     *     per_tahun: array<int, int>,
     *     terpopuler: array<string, int>
     * }
     */
    public static function untuk(User $pengguna): array
    {
        $lingkup = LingkupLaporan::untuk($pengguna);
        $kunci = self::kunci($lingkup);

        self::catatKunci($kunci);

        return Cache::remember($kunci, self::TTL_DETIK, fn (): array => self::hitung($lingkup));
    }

    public static function kunci(LingkupLaporan $lingkup): string
    {
        return "dasbor:{$lingkup->peran}:{$lingkup->hashKategori()}";
    }

    /**
     * Hapus semua cache dasbor (dipanggil dari CacheRegulasi::lupakanPublik()).
     */
    public static function lupakan(): void
    {
        foreach ((array) Cache::get(self::KUNCI_INDEKS, []) as $kunci) {
            Cache::forget((string) $kunci);
        }

        Cache::forget(self::KUNCI_INDEKS);
    }

    /**
     * @return array{
     *     total_terbit: int,
     *     menunggu_verifikasi: int,
     *     dicabut: int,
     *     dibuka_30_hari: int,
     *     persen_abstrak: int|null,
     *     persen_teks: int|null,
     *     persen_lengkap: int|null,
     *     per_jenis: list<array{nama: string, jumlah: int}>,
     *     per_tahun: array<int, int>,
     *     terpopuler: array<string, int>
     * }
     */
    public static function hitung(LingkupLaporan $lingkup): array
    {
        $dokumen = fn (): Builder => $lingkup->terapkan(Dokumen::query());
        $terbit = fn (): Builder => $dokumen()->where('dokumen.status_publikasi', StatusPublikasi::Terbit->value);

        $kelengkapan = $terbit()->toBase()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN '.KriteriaKelengkapan::sql('abstrak').' THEN 1 ELSE 0 END) AS abstrak')
            ->selectRaw('SUM(CASE WHEN '.KriteriaKelengkapan::sql('teks').' THEN 1 ELSE 0 END) AS teks')
            ->selectRaw('SUM(CASE WHEN '.KriteriaKelengkapan::sql('abstrak').' AND '.KriteriaKelengkapan::sql('teks').' THEN 1 ELSE 0 END) AS keduanya')
            ->first();
        $total = (int) ($kelengkapan->total ?? 0);
        $persen = fn (string $kolom): ?int => $total === 0 ? null : (int) round(((int) ($kelengkapan->{$kolom} ?? 0)) * 100 / $total);

        $perJenis = $terbit()->toBase()
            ->join('jenis_dokumen', 'jenis_dokumen.id', '=', 'dokumen.jenis_dokumen_id')
            ->groupBy('jenis_dokumen.id', 'jenis_dokumen.nama', 'jenis_dokumen.urutan')
            ->orderBy('jenis_dokumen.urutan')
            ->orderBy('jenis_dokumen.nama')
            ->selectRaw('jenis_dokumen.nama AS nama, COUNT(*) AS jumlah')
            ->get()
            ->map(fn (object $b): array => ['nama' => (string) $b->nama, 'jumlah' => (int) $b->jumlah])
            ->values()
            ->all();

        $tahunAkhir = (int) now()->year;
        $tahunAwal = $tahunAkhir - self::RENTANG_TAHUN + 1;
        $mentahPerTahun = $terbit()->toBase()
            ->whereBetween('dokumen.tahun', [$tahunAwal, $tahunAkhir])
            ->groupBy('dokumen.tahun')
            ->selectRaw('dokumen.tahun AS tahun, COUNT(*) AS jumlah')
            ->pluck('jumlah', 'tahun');
        $perTahun = [];

        for ($tahun = $tahunAwal; $tahun <= $tahunAkhir; $tahun++) {
            $perTahun[$tahun] = (int) ($mentahPerTahun[$tahun] ?? 0);
        }

        $log = fn () => $lingkup->terapkanPadaKolom(
            DB::table('log_buka_tautan')->where('log_buka_tautan.created_at', '>=', now()->subDays(self::HARI_PEMBUKAAN)),
            'log_buka_tautan.dokumen_id',
        );

        $terpopuler = $log()
            ->groupBy('log_buka_tautan.dokumen_id')
            ->selectRaw('log_buka_tautan.dokumen_id AS dokumen_id, COUNT(*) AS jumlah')
            ->orderByDesc('jumlah')
            ->limit(self::JUMLAH_TERPOPULER)
            ->get()
            ->mapWithKeys(fn (object $b): array => [(string) $b->dokumen_id => (int) $b->jumlah])
            ->all();

        return [
            'total_terbit' => $total,
            'menunggu_verifikasi' => $dokumen()->where('dokumen.status_publikasi', StatusPublikasi::MenungguVerifikasi->value)->count(),
            'dicabut' => $terbit()->where('dokumen.status_berlaku', StatusBerlaku::Dicabut->value)->count(),
            'dibuka_30_hari' => $log()->count(),
            'persen_abstrak' => $persen('abstrak'),
            'persen_teks' => $persen('teks'),
            'persen_lengkap' => $persen('keduanya'),
            'per_jenis' => $perJenis,
            'per_tahun' => $perTahun,
            'terpopuler' => $terpopuler,
        ];
    }

    private static function catatKunci(string $kunci): void
    {
        $indeks = (array) Cache::get(self::KUNCI_INDEKS, []);

        if (! in_array($kunci, $indeks, true)) {
            $indeks[] = $kunci;
            Cache::forever(self::KUNCI_INDEKS, $indeks);
        }
    }
}
