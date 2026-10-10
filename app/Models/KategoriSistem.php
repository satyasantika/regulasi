<?php

namespace App\Models;

use App\Concerns\MemilikiSlug;
use App\Observers\MasterCacheObserver;
use Database\Factories\KategoriSistemFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Kategori sistem FKIP tempat dokumen dipakai (03-SKEMA §3.4); kontributor ditugaskan per kategori (BR-03).
 *
 * @property string $id
 * @property string $kode
 * @property string $nama
 * @property string $slug
 * @property string|null $deskripsi
 * @property string|null $ikon
 * @property string|null $warna
 * @property int $urutan
 * @property bool $aktif
 */
#[ObservedBy(MasterCacheObserver::class)]
class KategoriSistem extends Model
{
    /** @use HasFactory<KategoriSistemFactory> */
    use HasFactory, HasUuids, LogsActivity, MemilikiSlug, SoftDeletes;

    protected $table = 'kategori_sistem';

    protected $fillable = ['kode', 'nama', 'slug', 'deskripsi', 'ikon', 'warna', 'urutan', 'aktif'];

    protected $attributes = ['urutan' => 999, 'aktif' => true];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'aktif' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges()->useLogName('master');
    }

    /**
     * Kontributor yang ditugaskan ke kategori ini.
     *
     * @return BelongsToMany<User, $this>
     */
    public function pengguna(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'kategori_sistem_user')->withTimestamps(updatedAt: false);
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
