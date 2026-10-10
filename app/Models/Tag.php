<?php

namespace App\Models;

use App\Concerns\MemilikiSlug;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Tag subjek dokumen (03-SKEMA §3.6).
 *
 * @property string $id
 * @property string $nama
 * @property string $slug
 */
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory, HasUuids, LogsActivity, MemilikiSlug;

    protected $table = 'tag';

    protected $fillable = ['nama', 'slug'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges()->useLogName('master');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function terurut(Builder $query): void
    {
        $query->orderBy('nama');
    }
}
