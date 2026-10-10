<?php

namespace App\Models;

use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Catatan ekspor Filament (tabel `exports`) dengan kunci UUIDv7. Berkas hasil di disk `tmp` (≤ 24 jam).
 *
 * @property string $id
 * @property string $user_id
 */
class Ekspor extends Export
{
    use HasUuids;

    protected $table = 'exports';
}
