<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Permission as PermissionDasar;

/**
 * Izin dengan primary key UUIDv7 (STANDAR-TEKNIS §4a butir 5).
 */
class Permission extends PermissionDasar
{
    use HasUuids;

    protected $table = 'permissions';
}
