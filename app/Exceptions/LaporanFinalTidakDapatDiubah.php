<?php

namespace App\Exceptions;

use LogicException;

/**
 * Dilempar bila laporan evaluasi berstatus final hendak diubah atau dihapus (US-LAP-03).
 */
class LaporanFinalTidakDapatDiubah extends LogicException
{
    public static function untuk(string $operasi): self
    {
        return new self("Laporan evaluasi yang sudah final tidak dapat {$operasi}.");
    }
}
