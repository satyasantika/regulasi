<?php

namespace App\Models;

use App\Concerns\MemilikiSlug;
use App\Enums\KelompokJenisDokumen;
use Database\Factories\JenisDokumenFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Jenis dokumen hukum/pendukung (03-SKEMA §3.2).
 *
 * @property string $id
 * @property string $kode
 * @property string $nama
 * @property string|null $singkatan
 * @property string $slug
 * @property KelompokJenisDokumen $kelompok
 * @property int $urutan
 * @property bool $wajib_nomor
 * @property string|null $deskripsi
 * @property bool $aktif
 */
class JenisDokumen extends Model
{
    /** @use HasFactory<JenisDokumenFactory> */
    use HasFactory, HasUuids, LogsActivity, MemilikiSlug, SoftDeletes;

    protected $table = 'jenis_dokumen';

    protected $fillable = ['kode', 'nama', 'singkatan', 'slug', 'kelompok', 'urutan', 'wajib_nomor', 'deskripsi', 'aktif'];

    protected $attributes = ['urutan' => 999, 'wajib_nomor' => true, 'aktif' => true];

    protected function casts(): array
    {
        return [
            'kelompok' => KelompokJenisDokumen::class,
            'urutan' => 'integer',
            'wajib_nomor' => 'boolean',
            'aktif' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges()->useLogName('master');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function aktif(Builder $query): void
    {
        $query->where('aktif', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function terurut(Builder $query): void
    {
        $query->orderBy('urutan')->orderBy('nama');
    }
}
