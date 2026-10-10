<?php

namespace App\Support\Laporan;

use App\Enums\JenisRelasi;
use App\Models\DokumenRelasi;
use Illuminate\Database\Eloquent\Builder;

/**
 * Laporan relasi status (F11.3, PRD §8 "Relasi status"): relasi mencabut/mengubah dilihat dari sisi sumber
 * (pencabut/pengubah) beserta target dan tanggal efektif, termasuk relasi tertunda (efektif = false).
 */
final class RelasiStatus
{
    public const string EFEKTIF = 'efektif';

    public const string TERTUNDA = 'tertunda';

    /**
     * @return Builder<DokumenRelasi>
     */
    public static function query(LingkupLaporan $lingkup, ?string $keadaan = null): Builder
    {
        $query = DokumenRelasi::query()
            ->with(['dokumen', 'dokumenTerkait'])
            ->whereIn('dokumen_relasi.jenis_relasi', [JenisRelasi::Mencabut->value, JenisRelasi::Mengubah->value]);

        if ($keadaan === self::EFEKTIF || $keadaan === self::TERTUNDA) {
            $query->where('dokumen_relasi.efektif', $keadaan === self::EFEKTIF);
        }

        if (! $lingkup->adalahGlobal()) {
            $query->where(function (Builder $q) use ($lingkup): void {
                $lingkup->terapkanPadaKolom($q->getQuery(), 'dokumen_relasi.dokumen_id');
                $q->orWhere(fn (Builder $atau) => $lingkup->terapkanPadaKolom($atau->getQuery(), 'dokumen_relasi.dokumen_terkait_id'));
            });
        }

        return $query;
    }

    public static function keDataEkspor(LingkupLaporan $lingkup, ?string $keadaan): DataEkspor
    {
        $baris = self::query($lingkup, $keadaan)
            ->orderBy('dokumen_relasi.efektif')
            ->orderByDesc('dokumen_relasi.efektif_pada')
            ->get()
            ->map(fn (DokumenRelasi $r): array => [
                $r->dokumenTerkait->sebutan_singkat,
                $r->dokumenTerkait->judul,
                $r->dokumenTerkait->status_berlaku->label(),
                $r->jenis_relasi->kebalikan()->label(),
                $r->dokumen->sebutan_singkat,
                $r->dokumen->judul,
                $r->efektif_pada?->translatedFormat('d F Y'),
                $r->efektif ? 'Efektif' : 'Tertunda',
                $r->keterangan,
            ])
            ->values()
            ->all();

        return new DataEkspor(
            'Relasi Status Dokumen',
            match ($keadaan) {
                self::EFEKTIF => 'Relasi efektif',
                self::TERTUNDA => 'Relasi tertunda (belum efektif)',
                default => 'Semua relasi mencabut/mengubah',
            },
            ['Dokumen', 'Judul dokumen', 'Status berlaku', 'Relasi', 'Oleh (pencabut/pengubah)', 'Judul pencabut/pengubah', 'Tanggal efektif', 'Keadaan', 'Keterangan'],
            $baris,
        );
    }
}
