<?php

namespace App\Models;

use App\Concerns\MemilikiSlug;
use App\Enums\TingkatBadanPenetap;
use App\Observers\MasterCacheObserver;
use Database\Factories\BadanPenetapFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Badan penetap / tajuk entri utama (T.E.U.) badan (03-SKEMA §3.3).
 *
 * @property string $id
 * @property string $nama
 * @property string $teu
 * @property string|null $singkatan
 * @property string $slug
 * @property TingkatBadanPenetap $tingkat
 * @property string|null $induk_id
 * @property string|null $kode_eksternal
 * @property int $urutan
 * @property bool $aktif
 */
#[ObservedBy(MasterCacheObserver::class)]
class BadanPenetap extends Model
{
    /** @use HasFactory<BadanPenetapFactory> */
    use HasFactory, HasUuids, LogsActivity, MemilikiSlug, SoftDeletes;

    protected $table = 'badan_penetap';

    protected $fillable = ['nama', 'teu', 'singkatan', 'slug', 'tingkat', 'induk_id', 'kode_eksternal', 'urutan', 'aktif'];

    protected $attributes = ['urutan' => 999, 'aktif' => true];

    protected function casts(): array
    {
        return [
            'tingkat' => TingkatBadanPenetap::class,
            'urutan' => 'integer',
            'aktif' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges()->useLogName('master');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function induk(): BelongsTo
    {
        return $this->belongsTo(self::class, 'induk_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function anak(): HasMany
    {
        return $this->hasMany(self::class, 'induk_id');
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
