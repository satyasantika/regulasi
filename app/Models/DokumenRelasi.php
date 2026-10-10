<?php

namespace App\Models;

use App\Enums\JenisRelasi;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Satu sisi relasi antardokumen (03-SKEMA §3.11). Selalu dibuat berpasangan oleh TambahRelasiDokumen (BR-07)
 * dan dihapus berpasangan oleh HapusRelasiDokumen; jangan menyisipkan/menghapus baris di luar Action itu.
 *
 * @property string $id
 * @property string $dokumen_id
 * @property string $dokumen_terkait_id
 * @property JenisRelasi $jenis_relasi
 * @property string|null $pasangan_id
 * @property bool $efektif
 * @property CarbonImmutable|null $efektif_pada
 * @property string|null $keterangan
 * @property string|null $dibuat_oleh
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Dokumen $dokumen
 * @property-read Dokumen $dokumenTerkait
 * @property-read DokumenRelasi|null $pasangan
 * @property-read User|null $pembuat
 */
class DokumenRelasi extends Model
{
    use HasUuids, LogsActivity;

    protected $table = 'dokumen_relasi';

    protected $fillable = [
        'dokumen_id', 'dokumen_terkait_id', 'jenis_relasi', 'pasangan_id', 'efektif', 'efektif_pada', 'keterangan', 'dibuat_oleh',
    ];

    protected $attributes = [
        'efektif' => false,
    ];

    protected function casts(): array
    {
        return [
            'jenis_relasi' => JenisRelasi::class,
            'efektif' => 'boolean',
            'efektif_pada' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['dokumen_id', 'dokumen_terkait_id', 'jenis_relasi', 'efektif', 'efektif_pada', 'keterangan'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('relasi');
    }

    /** @return BelongsTo<Dokumen, $this> */
    public function dokumen(): BelongsTo
    {
        return $this->belongsTo(Dokumen::class, 'dokumen_id')->withTrashed();
    }

    /** @return BelongsTo<Dokumen, $this> */
    public function dokumenTerkait(): BelongsTo
    {
        return $this->belongsTo(Dokumen::class, 'dokumen_terkait_id')->withTrashed();
    }

    /** @return BelongsTo<DokumenRelasi, $this> */
    public function pasangan(): BelongsTo
    {
        return $this->belongsTo(DokumenRelasi::class, 'pasangan_id');
    }

    /** @return BelongsTo<User, $this> */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /**
     * Id dokumen sumber (pemilik arah aktif) dan target dari baris ini, apa pun sisinya.
     *
     * @return array{sumber: string, target: string}
     */
    public function arah(): array
    {
        return $this->jenis_relasi->adalahArahAktif()
            ? ['sumber' => $this->dokumen_id, 'target' => $this->dokumen_terkait_id]
            : ['sumber' => $this->dokumen_terkait_id, 'target' => $this->dokumen_id];
    }
}
