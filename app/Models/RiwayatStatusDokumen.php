<?php

namespace App\Models;

use App\Enums\JenisRiwayatStatus;
use App\Exceptions\RiwayatTidakDapatDiubah;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat perubahan status publikasi/berlaku dokumen (03-SKEMA §3.12). Append-only: tanpa updated_at,
 * pengubahan dan penghapusan lewat model ditolak. Hanya ditulis oleh Action (F5/F6).
 *
 * @property string $id
 * @property string $dokumen_id
 * @property JenisRiwayatStatus $jenis
 * @property string|null $dari
 * @property string $ke
 * @property string|null $catatan
 * @property string|null $dokumen_relasi_id
 * @property string|null $oleh
 * @property CarbonImmutable $created_at
 * @property-read Dokumen $dokumen
 * @property-read User|null $pengguna
 */
class RiwayatStatusDokumen extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'riwayat_status_dokumen';

    protected $fillable = ['dokumen_id', 'jenis', 'dari', 'ke', 'catatan', 'dokumen_relasi_id', 'oleh'];

    protected function casts(): array
    {
        return [
            'jenis' => JenisRiwayatStatus::class,
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw RiwayatTidakDapatDiubah::untuk('diubah'));
        static::deleting(fn (): never => throw RiwayatTidakDapatDiubah::untuk('dihapus'));
    }

    /** @return BelongsTo<Dokumen, $this> */
    public function dokumen(): BelongsTo
    {
        return $this->belongsTo(Dokumen::class, 'dokumen_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'oleh');
    }

    public function labelDari(): ?string
    {
        return $this->jenis->labelStatus($this->dari);
    }

    public function labelKe(): ?string
    {
        return $this->jenis->labelStatus($this->ke);
    }
}
