<?php

namespace App\Exceptions;

use LogicException;

/**
 * Dilempar bila baris riwayat_status_dokumen hendak diubah atau dihapus (append-only, 03-SKEMA §3.12).
 */
class RiwayatTidakDapatDiubah extends LogicException
{
    public static function untuk(string $operasi): self
    {
        return new self("Riwayat status dokumen bersifat append-only dan tidak dapat {$operasi}.");
    }
}
