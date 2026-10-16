<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Pasangan sinonim pencarian satu arah (03-SKEMA §3.15b); pasangan dua arah disimpan sebagai dua baris.
 *
 * @property string $id
 * @property string $istilah
 * @property string $sinonim
 */
class SinonimPencarian extends Model
{
    use HasUuids;

    protected $table = 'sinonim_pencarian';

    protected $fillable = ['istilah', 'sinonim'];
}
