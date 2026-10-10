<?php

namespace App\Models;

use App\Enums\JenisTautan;
use App\Enums\PenyediaTautan;
use App\Enums\StatusCekTautan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Tautan berkas (03-SKEMA §3.10, BR-13). Tidak ada berkas di server; perubahan url/label/jenis tercatat
 * activitylog (lama → baru). Disimpan hanya lewat SimpanTautanBerkas/HapusTautanBerkas.
 *
 * @property string $id
 * @property string $pemilik_type
 * @property string $pemilik_id
 * @property JenisTautan $jenis
 * @property string|null $label
 * @property string $url
 * @property PenyediaTautan $penyedia
 * @property string|null $drive_file_id
 * @property StatusCekTautan $status_cek
 * @property CarbonImmutable|null $dicek_pada
 * @property int $urutan
 * @property int $jumlah_dibuka
 * @property string|null $ditambahkan_oleh
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Model|null $pemilik
 */
class TautanBerkas extends Model
{
    use HasUuids, LogsActivity, SoftDeletes;

    protected $table = 'tautan_berkas';

    protected $fillable = [
        'pemilik_type', 'pemilik_id', 'jenis', 'label', 'url', 'penyedia', 'drive_file_id', 'urutan', 'ditambahkan_oleh',
    ];

    protected $attributes = [
        'jenis' => 'lampiran',
        'status_cek' => 'belum',
        'urutan' => 0,
        'jumlah_dibuka' => 0,
    ];

    protected function casts(): array
    {
        return [
            'jenis' => JenisTautan::class,
            'penyedia' => PenyediaTautan::class,
            'status_cek' => StatusCekTautan::class,
            'dicek_pada' => 'datetime',
            'urutan' => 'integer',
            'jumlah_dibuka' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['url', 'label', 'jenis'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('tautan');
    }

    /** @return MorphTo<Model, $this> */
    public function pemilik(): MorphTo
    {
        return $this->morphTo('pemilik');
    }

    /** @return BelongsTo<User, $this> */
    public function penambah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditambahkan_oleh');
    }

    /**
     * Dokumen pemilik (di sistem ini pemilik selalu Dokumen), termasuk yang dihapus lunak.
     */
    public function dokumen(): ?Dokumen
    {
        if ($this->relationLoaded('pemilik')) {
            $pemilik = $this->getRelation('pemilik');

            return $pemilik instanceof Dokumen ? $pemilik : null;
        }

        if ($this->pemilik_type !== (new Dokumen)->getMorphClass()) {
            return null;
        }

        $dokumen = Dokumen::withTrashed()->find($this->pemilik_id);
        $this->setRelation('pemilik', $dokumen);

        return $dokumen;
    }
}
