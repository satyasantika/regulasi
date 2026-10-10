<?php

namespace App\Support;

use App\Enums\JenisRelasi;
use App\Enums\StatusBerlaku;
use App\Enums\StatusPublikasi;
use App\Models\Dokumen;
use App\Models\DokumenRelasi;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Kalimat ringkas status berlaku dokumen dari relasinya (F6.3), mis.
 * "Dicabut oleh BAN-PT 13/2025 sejak 18 Agustus 2025" atau "Berlaku; diubah oleh PP 28/2020",
 * serta daftar relasi tertunda ("akan mencabut … mulai 1 Januari 2027").
 */
class RingkasanStatus
{
    /**
     * @return array{kalimat: string, tertunda: list<string>}
     */
    public static function untuk(Dokumen $dokumen): array
    {
        /** @var Collection<int, DokumenRelasi> $relasi */
        $relasi = $dokumen->relasi()->with('dokumenTerkait')->orderBy('efektif_pada')->orderBy('created_at')->get();

        return [
            'kalimat' => self::kalimat($dokumen, $relasi),
            'tertunda' => self::tertunda($dokumen, $relasi),
        ];
    }

    /**
     * @param  Collection<int, DokumenRelasi>  $relasi
     */
    public static function kalimat(Dokumen $dokumen, Collection $relasi): string
    {
        if ($dokumen->status_berlaku_manual && $dokumen->status_berlaku === StatusBerlaku::TidakBerlaku) {
            return 'Tidak berlaku (ditetapkan manual oleh admin).';
        }

        $efektif = fn (JenisRelasi $jenis): Collection => $relasi->filter(fn (DokumenRelasi $r): bool => $r->efektif && $r->jenis_relasi === $jenis)->values();

        return match ($dokumen->status_berlaku) {
            StatusBerlaku::Dicabut => ($pencabut = $efektif(JenisRelasi::DicabutOleh))->isNotEmpty()
                ? 'Dicabut oleh '.self::gabung($pencabut->map(fn (DokumenRelasi $r): string => $r->dokumenTerkait->sebutan_singkat.self::sejak($r->efektif_pada))->all()).'.'
                : 'Dicabut.',
            StatusBerlaku::Diubah => ($pengubah = $efektif(JenisRelasi::DiubahOleh))->isNotEmpty()
                ? 'Berlaku; diubah oleh '.self::gabung($pengubah->map(fn (DokumenRelasi $r): string => $r->dokumenTerkait->sebutan_singkat)->all()).'.'
                : 'Berlaku dengan perubahan.',
            StatusBerlaku::TidakBerlaku => 'Tidak berlaku.',
            StatusBerlaku::Berlaku => 'Berlaku.',
        };
    }

    /**
     * Relasi yang sumbernya sudah terbit tetapi tanggal efektifnya belum tiba (BR-08).
     *
     * @param  Collection<int, DokumenRelasi>  $relasi
     * @return list<string>
     */
    public static function tertunda(Dokumen $dokumen, Collection $relasi): array
    {
        $hariIni = CarbonImmutable::today();

        return $relasi
            ->filter(function (DokumenRelasi $r) use ($dokumen, $hariIni): bool {
                $sumber = $r->jenis_relasi->adalahArahAktif() ? $dokumen : $r->dokumenTerkait;

                return ! $r->efektif
                    && $r->efektif_pada !== null
                    && $r->efektif_pada->gt($hariIni)
                    && $sumber->status_publikasi === StatusPublikasi::Terbit;
            })
            ->map(fn (DokumenRelasi $r): string => ($r->jenis_relasi->adalahArahAktif() ? 'Dokumen ini akan ' : 'Akan ')
                .mb_strtolower($r->jenis_relasi->label()).' '.$r->dokumenTerkait->sebutan_singkat
                .' mulai '.$r->efektif_pada?->translatedFormat('j F Y').'.')
            ->values()
            ->all();
    }

    private static function sejak(?CarbonImmutable $tanggal): string
    {
        return $tanggal === null ? '' : ' sejak '.$tanggal->translatedFormat('j F Y');
    }

    /**
     * @param  array<int, string>  $bagian
     */
    private static function gabung(array $bagian): string
    {
        $bagian = array_values($bagian);

        if (count($bagian) <= 1) {
            return $bagian[0] ?? '';
        }

        $akhir = array_pop($bagian);

        return implode(', ', $bagian).' dan '.$akhir;
    }
}
