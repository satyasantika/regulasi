<?php

namespace App\Filament\Admin\Exports;

use App\Models\Dokumen;
use App\Support\Laporan\KriteriaKelengkapan;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ekspor Excel laporan kelengkapan metadata (F11.2, US-LAP-02) lewat Filament ExportAction: berbasis antrean
 * `berat` (koneksi `database-berat`), berkas di disk `tmp` (dihapus ≤ 24 jam), header kolom berbahasa Indonesia.
 */
class KelengkapanDokumenExporter extends Exporter
{
    protected static ?string $model = Dokumen::class;

    public static function getColumns(): array
    {
        $kriteria = array_map(
            fn (string $kunci, string $label): ExportColumn => ExportColumn::make('lengkap_'.$kunci)
                ->label($label)
                ->formatStateUsing(fn (mixed $state): string => (bool) $state ? 'Ya' : 'Tidak'),
            array_keys(KriteriaKelengkapan::LABEL),
            KriteriaKelengkapan::LABEL,
        );

        return [
            ExportColumn::make('sebutan_singkat')->label('Sebutan'),
            ExportColumn::make('judul')->label('Judul'),
            ExportColumn::make('jenisDokumen.nama')->label('Jenis dokumen'),
            ExportColumn::make('tahun')->label('Tahun'),
            ExportColumn::make('kategoriSistem.nama')->label('Kategori sistem'),
            ...$kriteria,
            ExportColumn::make('skor_kelengkapan')->label('Skor kelengkapan (%)'),
        ];
    }

    /**
     * Pastikan kolom kelengkapan terhitung walau query tabel tidak memuatnya.
     *
     * @param  Builder<Dokumen>  $query
     * @return Builder<Dokumen>
     */
    public static function modifyQuery(Builder $query): Builder
    {
        $kolom = collect($query->getQuery()->columns ?? [])->map(fn (mixed $k): string => is_string($k) ? $k : (string) $query->getGrammar()->getValue($k));

        return $kolom->contains(fn (string $k): bool => str_contains($k, 'skor_kelengkapan'))
            ? $query
            : KriteriaKelengkapan::pilihKolom($query);
    }

    public function getJobConnection(): ?string
    {
        return 'database-berat';
    }

    public function getJobQueue(): ?string
    {
        return 'berat';
    }

    public function getFileDisk(): string
    {
        return 'tmp';
    }

    public function getFileName(Export $export): string
    {
        return 'kelengkapan-metadata-'.now()->format('Ymd-His');
    }

    public static function getCompletedNotificationTitle(Export $export): string
    {
        return 'Ekspor kelengkapan metadata selesai';
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $isi = number_format($export->successful_rows, 0, ',', '.').' dokumen diekspor. Berkas tersedia selama 24 jam.';

        if (($gagal = $export->getFailedRowsCount()) > 0) {
            $isi .= ' '.number_format($gagal, 0, ',', '.').' baris gagal diekspor.';
        }

        return $isi;
    }
}
