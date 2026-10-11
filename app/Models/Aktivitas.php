<?php

namespace App\Models;

use App\Support\Impersonasi;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Activitylog\Models\Activity;

/**
 * Entri jejak audit dengan primary key UUIDv7 (STANDAR-TEKNIS §4a butir 5). Selama impersonasi, setiap entri
 * memuat `impersonasi_oleh` (id super-admin asal) di properties.
 */
class Aktivitas extends Activity
{
    use HasUuids;

    protected $table = 'activity_log';

    protected static function booted(): void
    {
        static::creating(function (Aktivitas $aktivitas): void {
            $asal = Impersonasi::idAsal();

            if ($asal !== null) {
                $aktivitas->properties = collect($aktivitas->properties)->put('impersonasi_oleh', $asal);
            }
        });
    }
}
