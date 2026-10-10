<?php

namespace App\Jobs;

use App\Enums\SaluranBuka;
use App\Models\Dokumen;
use App\Models\LogBukaTautan;
use App\Models\TautanBerkas;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Mencatat pembukaan tautan (BR-17): ip_hash = SHA-256(IP + APP_KEY) — IP mentah tidak disimpan —
 * lalu menambah jumlah_dibuka tautan dan dokumen tanpa menyentuh updated_at/log aktivitas.
 * Dijalankan setelah respons dikirim (dispatch()->afterResponse()).
 */
class CatatBukaTautan implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $tautanBerkasId,
        public string $dokumenId,
        public ?string $userId,
        public string $ipHash,
        public ?string $userAgent,
        public SaluranBuka $saluran = SaluranBuka::Web,
        public ?string $klienApiId = null,
    ) {}

    public static function hashIp(?string $ip): string
    {
        return hash('sha256', ((string) $ip).config('app.key'));
    }

    public function handle(): void
    {
        DB::transaction(function (): void {
            LogBukaTautan::create([
                'dokumen_id' => $this->dokumenId,
                'tautan_berkas_id' => $this->tautanBerkasId,
                'user_id' => $this->userId,
                'klien_api_id' => $this->klienApiId,
                'saluran' => $this->saluran,
                'ip_hash' => $this->ipHash,
                'user_agent' => $this->userAgent !== null ? mb_substr($this->userAgent, 0, 255) : null,
            ]);

            TautanBerkas::withTrashed()->whereKey($this->tautanBerkasId)->toBase()->increment('jumlah_dibuka');
            Dokumen::withTrashed()->whereKey($this->dokumenId)->toBase()->increment('jumlah_dibuka');
        });
    }
}
