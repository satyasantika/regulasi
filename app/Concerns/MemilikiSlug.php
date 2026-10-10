<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Slug otomatis dari kolom `nama` saat dibuat bila slug kosong; dijamin unik
 * (termasuk baris yang dihapus lunak, karena indeks UNIQUE mencakup semuanya).
 *
 * @phpstan-require-extends Model
 */
trait MemilikiSlug
{
    public static function bootMemilikiSlug(): void
    {
        static::creating(function (Model $model): void {
            if (blank($model->getAttribute('slug'))) {
                $model->setAttribute('slug', static::slugUnik((string) $model->getAttribute('nama')));
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public static function slugUnik(string $nama): string
    {
        $dasar = Str::slug(str_replace('/', ' ', $nama)) ?: 'tanpa-nama';
        $slug = $dasar;
        $nomor = 2;

        while (static::slugTerpakai($slug)) {
            $slug = "{$dasar}-{$nomor}";
            $nomor++;
        }

        return $slug;
    }

    protected static function slugTerpakai(string $slug): bool
    {
        $query = static::query();

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            $query->withoutGlobalScopes();
        }

        return $query->where('slug', $slug)->exists();
    }
}
