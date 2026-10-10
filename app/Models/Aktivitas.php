<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Activitylog\Models\Activity;

/**
 * Entri jejak audit dengan primary key UUIDv7 (STANDAR-TEKNIS §4a butir 5).
 */
class Aktivitas extends Activity
{
    use HasUuids;

    protected $table = 'activity_log';
}
