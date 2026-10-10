<?php

namespace App\Support\Laporan;

use App\Enums\SaluranBuka;
use App\Models\Dokumen;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Agregat pembukaan tautan dari log_buka_tautan (F11.3, PRD §8 "Log buka tautan"): per dokumen (web, api,
 * total), harian, dan total. Kolom ip_hash/user_agent tidak pernah dipilih (BR-17).
 */
final class PembukaanTautan
{
    public const int HARI_BAWAAN = 30;

    public const int MAKS_HARI = 366;

    /**
     * Periode dan saringan dari state filter tabel (bawaan 30 hari terakhir).
     *
     * @param  array<string, mixed>  $filters
     * @return array{dari: CarbonImmutable, sampai: CarbonImmutable, saluran: string|null, kategori: string|null}
     */
    public static function saringan(array $filters): array
    {
        $tanggal = fn (mixed $nilai): ?CarbonImmutable => is_string($nilai) && $nilai !== '' ? CarbonImmutable::parse($nilai) : null;
        $sampai = ($tanggal($filters['periode']['sampai'] ?? null) ?? CarbonImmutable::today())->startOfDay();
        $dari = ($tanggal($filters['periode']['dari'] ?? null) ?? $sampai->subDays(self::HARI_BAWAAN - 1))->startOfDay();

        if ($dari->greaterThan($sampai)) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        if ($dari->diffInDays($sampai) >= self::MAKS_HARI) {
            $dari = $sampai->subDays(self::MAKS_HARI - 1);
        }

        $saluran = $filters['saluran']['value'] ?? null;
        $kategori = $filters['kategori']['value'] ?? null;

        return [
            'dari' => $dari,
            'sampai' => $sampai,
            'saluran' => is_string($saluran) && SaluranBuka::tryFrom($saluran) !== null ? $saluran : null,
            'kategori' => is_string($kategori) && $kategori !== '' ? $kategori : null,
        ];
    }

    /**
     * @param  array{dari: CarbonImmutable, sampai: CarbonImmutable, saluran: string|null, kategori: string|null}  $saringan
     */
    public static function log(LingkupLaporan $lingkup, array $saringan): QueryBuilder
    {
        $query = DB::table('log_buka_tautan')
            ->where('log_buka_tautan.created_at', '>=', $saringan['dari']->startOfDay())
            ->where('log_buka_tautan.created_at', '<=', $saringan['sampai']->endOfDay());

        if ($saringan['saluran'] !== null) {
            $query->where('log_buka_tautan.saluran', $saringan['saluran']);
        }

        if ($saringan['kategori'] !== null) {
            $kategori = $saringan['kategori'];
            $query->whereIn('log_buka_tautan.dokumen_id', fn ($sub) => $sub->select('dokumen_id')
                ->from('dokumen_kategori_sistem')
                ->where('kategori_sistem_id', $kategori));
        }

        $lingkup->terapkanPadaKolom($query, 'log_buka_tautan.dokumen_id');

        return $query;
    }

    /**
     * Dokumen beserta kolom dibuka_web, dibuka_api, dibuka_total pada periode (hanya yang pernah dibuka).
     *
     * @param  array{dari: CarbonImmutable, sampai: CarbonImmutable, saluran: string|null, kategori: string|null}  $saringan
     * @return Builder<Dokumen>
     */
    public static function perDokumen(LingkupLaporan $lingkup, array $saringan): Builder
    {
        $agregat = self::log($lingkup, $saringan)
            ->groupBy('log_buka_tautan.dokumen_id')
            ->select('log_buka_tautan.dokumen_id')
            ->selectRaw("SUM(CASE WHEN log_buka_tautan.saluran = 'web' THEN 1 ELSE 0 END) AS dibuka_web")
            ->selectRaw("SUM(CASE WHEN log_buka_tautan.saluran = 'api' THEN 1 ELSE 0 END) AS dibuka_api")
            ->selectRaw('COUNT(*) AS dibuka_total');

        return Dokumen::query()
            ->withTrashed()
            ->with('jenisDokumen')
            ->joinSub($agregat, 'buka', 'buka.dokumen_id', '=', 'dokumen.id')
            ->select('dokumen.*', 'buka.dibuka_web', 'buka.dibuka_api', 'buka.dibuka_total');
    }

    /**
     * Jumlah pembukaan per hari (Y-m-d → jumlah), termasuk hari tanpa pembukaan.
     *
     * @param  array{dari: CarbonImmutable, sampai: CarbonImmutable, saluran: string|null, kategori: string|null}  $saringan
     * @return array<string, int>
     */
    public static function harian(LingkupLaporan $lingkup, array $saringan): array
    {
        $mentah = self::log($lingkup, $saringan)
            ->groupByRaw('DATE(log_buka_tautan.created_at)')
            ->selectRaw('DATE(log_buka_tautan.created_at) AS hari, COUNT(*) AS jumlah')
            ->pluck('jumlah', 'hari');
        $hasil = [];

        for ($hari = $saringan['dari']; $hari->lessThanOrEqualTo($saringan['sampai']); $hari = $hari->addDay()) {
            $hasil[$hari->toDateString()] = (int) ($mentah[$hari->toDateString()] ?? 0);
        }

        return $hasil;
    }

    /**
     * @param  array{dari: CarbonImmutable, sampai: CarbonImmutable, saluran: string|null, kategori: string|null}  $saringan
     * @return array{web: int, api: int, total: int}
     */
    public static function total(LingkupLaporan $lingkup, array $saringan): array
    {
        $baris = self::log($lingkup, $saringan)
            ->selectRaw("SUM(CASE WHEN saluran = 'web' THEN 1 ELSE 0 END) AS web, SUM(CASE WHEN saluran = 'api' THEN 1 ELSE 0 END) AS api, COUNT(*) AS total")
            ->first();

        return ['web' => (int) ($baris->web ?? 0), 'api' => (int) ($baris->api ?? 0), 'total' => (int) ($baris->total ?? 0)];
    }

    /**
     * @param  array{dari: CarbonImmutable, sampai: CarbonImmutable, saluran: string|null, kategori: string|null}  $saringan
     */
    public static function labelPeriode(array $saringan): string
    {
        return $saringan['dari']->translatedFormat('d F Y').' – '.$saringan['sampai']->translatedFormat('d F Y');
    }
}
