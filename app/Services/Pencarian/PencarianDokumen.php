<?php

namespace App\Services\Pencarian;

/**
 * Satu antarmuka pencarian untuk portal & API (02-ARSITEKTUR §5.2). Implementasi: PencarianMeilisearch dan
 * cadangan PencarianDatabase (MariaDB FULLTEXT), dipilih PemilihMesinPencarian. Hanya dokumen terbit (BR-01);
 * dokumen internal hanya bila sertakanInternal (BR-02).
 */
interface PencarianDokumen
{
    public function cari(KriteriaPencarian $kriteria): HasilPencarian;
}
