<?php

namespace App\Services\Pencarian;

use App\Models\Dokumen;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Hasil pencarian: dokumen ter-eager-load (urutan mesin dipertahankan), faset per dimensi, dan mesin yang menjawab.
 */
final readonly class HasilPencarian
{
    public const array DIMENSI_FASET = ['jenis', 'tahun', 'status_berlaku', 'kategori', 'badan_penetap'];

    /**
     * @param  LengthAwarePaginator<int, Dokumen>  $dokumen
     * @param  array<string, array<string, int>>  $faset
     * @param  'database'  $mesin
     * @param  string|null  $dikoreksiDari  kueri asli bila hasil memakai kueri terkoreksi kamus istilah
     */
    public function __construct(
        public LengthAwarePaginator $dokumen,
        public array $faset,
        public string $mesin,
        public ?string $kueriDipakai = null,
        public ?string $dikoreksiDari = null,
    ) {}
}
