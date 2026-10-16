<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu istilah kamus pencarian (03-SKEMA §3.15a). Dibangun ulang oleh BangunKamus; tidak diedit manual.
 *
 * @property string $id
 * @property string $istilah
 * @property string $sumber
 * @property int $frekuensi
 */
class IstilahPencarian extends Model
{
    use HasUuids;

    protected $table = 'istilah_pencarian';

    protected $fillable = ['istilah', 'sumber', 'frekuensi'];

    protected function casts(): array
    {
        return ['frekuensi' => 'integer'];
    }
}
