<?php

namespace App\Models;

use App\Enums\SaluranBuka;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log pembukaan tautan berkas (03-SKEMA §3.13, BR-17). Append-only, tanpa updated_at;
 * dipangkas `model:prune` setelah config('regulasi.retensi_log_buka_bulan') bulan.
 *
 * @property string $id
 * @property string $dokumen_id
 * @property string $tautan_berkas_id
 * @property string|null $user_id
 * @property string|null $klien_api_id
 * @property SaluranBuka $saluran
 * @property string $ip_hash
 * @property string|null $user_agent
 * @property CarbonImmutable $created_at
 */
class LogBukaTautan extends Model
{
    use HasUuids, MassPrunable;

    public const UPDATED_AT = null;

    protected $table = 'log_buka_tautan';

    protected $fillable = ['dokumen_id', 'tautan_berkas_id', 'user_id', 'klien_api_id', 'saluran', 'ip_hash', 'user_agent'];

    protected function casts(): array
    {
        return [
            'saluran' => SaluranBuka::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subMonths((int) config('regulasi.retensi_log_buka_bulan', 24)));
    }

    /** @return BelongsTo<Dokumen, $this> */
    public function dokumen(): BelongsTo
    {
        return $this->belongsTo(Dokumen::class, 'dokumen_id');
    }

    /** @return BelongsTo<TautanBerkas, $this> */
    public function tautanBerkas(): BelongsTo
    {
        return $this->belongsTo(TautanBerkas::class, 'tautan_berkas_id');
    }

    /** @return BelongsTo<User, $this> */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<KlienApi, $this> */
    public function klienApi(): BelongsTo
    {
        return $this->belongsTo(KlienApi::class, 'klien_api_id')->withTrashed();
    }
}
