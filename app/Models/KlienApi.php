<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\KlienApiFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Klien API: sistem FKIP lain pemilik token Sanctum read-only (03-SKEMA §3.14, US-API-02, BR-18).
 * Token di personal_access_tokens (tokenable_type = alias morph `klien_api`), abilities `dokumen:baca`
 * (wajib) dan `dokumen:baca-internal` (opsional). Klien nonaktif → 401 (PastikanKlienApiAktif).
 *
 * @property string $id
 * @property string $nama
 * @property string|null $kategori_sistem_id
 * @property string|null $kontak_nama
 * @property string|null $kontak_email
 * @property bool $aktif
 * @property CarbonImmutable|null $terakhir_dipakai_pada
 * @property string|null $catatan
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read KategoriSistem|null $kategoriSistem
 * @property-read Collection<int, TokenAkses> $tokens
 */
class KlienApi extends Model implements AuthenticatableContract
{
    /** @use HasFactory<KlienApiFactory> */
    use Authenticatable, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    /** @use HasApiTokens<TokenAkses> */
    use HasApiTokens;

    public const string ABILITY_BACA = 'dokumen:baca';

    public const string ABILITY_BACA_INTERNAL = 'dokumen:baca-internal';

    /** Masa berlaku token yang dapat dipilih (hari). */
    public const array MASA_BERLAKU_HARI = [90, 180, 365];

    protected $table = 'klien_api';

    protected $fillable = ['nama', 'kategori_sistem_id', 'kontak_nama', 'kontak_email', 'aktif', 'catatan'];

    protected $attributes = ['aktif' => true];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'terakhir_dipakai_pada' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nama', 'kategori_sistem_id', 'kontak_nama', 'kontak_email', 'aktif', 'catatan'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('klien-api');
    }

    /**
     * Kategori sistem FKIP asal klien (mis. Aset).
     *
     * @return BelongsTo<KategoriSistem, $this>
     */
    public function kategoriSistem(): BelongsTo
    {
        return $this->belongsTo(KategoriSistem::class, 'kategori_sistem_id')->withTrashed();
    }
}
