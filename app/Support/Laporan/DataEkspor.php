<?php

namespace App\Support\Laporan;

/**
 * Data tabular siap ditulis ke Excel (judul, kolom berbahasa Indonesia, baris).
 */
final readonly class DataEkspor
{
    /**
     * @param  list<string>  $kolom
     * @param  list<list<string|int|float|null>>  $baris
     */
    public function __construct(
        public string $judul,
        public string $subjudul,
        public array $kolom,
        public array $baris,
    ) {}
}
