<?php

namespace App\Support\Laporan;

use Spatie\SimpleExcel\SimpleExcelWriter;

/**
 * Menulis DataEkspor ke berkas xlsx (spatie/simple-excel). Berkas selalu sementara (storage/app/tmp).
 */
final class PenulisExcel
{
    public static function tulis(DataEkspor $data, string $jalur): void
    {
        $penulis = SimpleExcelWriter::create($jalur, 'xlsx');
        $penulis->addHeader($data->kolom);

        foreach ($data->baris as $baris) {
            $penulis->addRow(self::gabung($data->kolom, $baris));
        }

        $kosong = array_fill(0, max(0, count($data->kolom) - 1), '');
        $penulis->addRow(self::gabung($data->kolom, ['', ...$kosong]));
        $penulis->addRow(self::gabung($data->kolom, [$data->judul.' — '.$data->subjudul, ...$kosong]));
        $penulis->addRow(self::gabung($data->kolom, ['Dibuat '.now()->translatedFormat('d F Y H:i').' WIB oleh Regulasi FKIP Unsil', ...$kosong]));

        $penulis->close();
    }

    /**
     * @param  list<string>  $kolom
     * @param  list<string|int|float|null>  $baris
     * @return array<string, string|int|float|null>
     */
    private static function gabung(array $kolom, array $baris): array
    {
        $hasil = [];

        foreach ($kolom as $i => $nama) {
            $hasil[$nama] = $baris[$i] ?? null;
        }

        return $hasil;
    }
}
