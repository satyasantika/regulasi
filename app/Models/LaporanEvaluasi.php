<?php

namespace App\Models;

use App\Enums\StatusLaporan;
use App\Exceptions\LaporanFinalTidakDapatDiubah;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Laporan evaluasi periode (03-SKEMA §3.15, US-LAP-03). `ringkasan` adalah snapshot angka (diisi
 * HitungRingkasanLaporan); laporan final tidak dapat diubah lagi. PDF dirender ulang dari snapshot saat diminta
 * dan tidak pernah disimpan (STANDAR-TEKNIS §1a.6).
 *
 * @property string $id
 * @property string $judul
 * @property CarbonImmutable $periode_mulai
 * @property CarbonImmutable $periode_selesai
 * @property array<string, mixed> $ringkasan
 * @property string|null $analisis
 * @property string|null $tindak_lanjut
 * @property StatusLaporan $status
 * @property string|null $dibuat_oleh
 * @property CarbonImmutable|null $difinalkan_pada
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $pembuat
 */
class LaporanEvaluasi extends Model
{
    use HasUuids, LogsActivity;

    protected $table = 'laporan_evaluasi';

    protected $fillable = ['judul', 'periode_mulai', 'periode_selesai', 'analisis', 'tindak_lanjut', 'dibuat_oleh'];

    protected $attributes = [
        'status' => 'draf',
    ];

    protected function casts(): array
    {
        return [
            'periode_mulai' => 'date',
            'periode_selesai' => 'date',
            'ringkasan' => 'array',
            'status' => StatusLaporan::class,
            'difinalkan_pada' => 'datetime',
        ];
    }

    /**
     * Laporan final terkunci (juga bagi super-admin yang lolos Gate::before).
     */
    protected static function booted(): void
    {
        static::updating(function (self $laporan): void {
            if ($laporan->getOriginal('status') === StatusLaporan::Final) {
                throw LaporanFinalTidakDapatDiubah::untuk('diubah');
            }
        });

        static::deleting(function (self $laporan): void {
            if ($laporan->getOriginal('status') === StatusLaporan::Final) {
                throw LaporanFinalTidakDapatDiubah::untuk('dihapus');
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['judul', 'periode_mulai', 'periode_selesai', 'analisis', 'tindak_lanjut', 'status', 'difinalkan_pada'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('laporan');
    }

    /** @return BelongsTo<User, $this> */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function adalahFinal(): bool
    {
        return $this->status === StatusLaporan::Final;
    }
}
