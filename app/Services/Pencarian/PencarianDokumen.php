<?php

namespace App\Services\Pencarian;

/**
 * Satu antarmuka pencarian untuk portal & API (02-ARSITEKTUR §5.1). Implementasi: PencarianDatabase
 * (MariaDB FULLTEXT + kamus istilah). Hanya dokumen terbit (BR-01);
 * dokumen internal hanya bila sertakanInternal (BR-02).
 */
interface PencarianDokumen
{
    public function cari(KriteriaPencarian $kriteria): HasilPencarian;
}
