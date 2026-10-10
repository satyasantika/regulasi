<?php

namespace App\Actions\Relasi;

use App\Enums\JenisRelasi;
use App\Enums\JenisRiwayatStatus;
use App\Enums\StatusBerlaku;
use App\Events\StatusBerlakuBerubah;
use App\Models\Dokumen;
use App\Models\DokumenRelasi;
use App\Models\RiwayatStatusDokumen;
use App\Models\User;
use App\Support\CacheRegulasi;
use Illuminate\Support\Facades\DB;

/**
 * Menghitung ulang status berlaku dari relasi efektif yang dimiliki dokumen (BR-09): dicabut > diubah > berlaku.
 * Status manual (`status_berlaku_manual`, mis. tidak_berlaku) tidak ditimpa.
 *
 * Dipanggil di dalam kunci `lock:status-dokumen:{id}` milik Action pemanggil (TambahRelasiDokumen,
 * HapusRelasiDokumen, UbahStatusBerlaku, TerapkanRelasiDokumen); baris dokumen dikunci ulang lockForUpdate().
 */
class HitungUlangStatusBerlaku
{
    /**
     * @return StatusBerlaku|null Status baru, atau null bila tidak berubah.
     */
    public function handle(Dokumen $dokumen, ?User $oleh = null, ?DokumenRelasi $pemicu = null, ?string $catatan = null): ?StatusBerlaku
    {
        return DB::transaction(function () use ($dokumen, $oleh, $pemicu, $catatan): ?StatusBerlaku {
            /** @var Dokumen $baris */
            $baris = Dokumen::withTrashed()->whereKey($dokumen->getKey())->lockForUpdate()->firstOrFail();

            if ($baris->status_berlaku_manual) {
                return null;
            }

            $asal = $baris->status_berlaku;
            $baru = $this->statusDariRelasi($baris);

            if ($asal === $baru) {
                return null;
            }

            $baris->status_berlaku = $baru;
            $baris->save();

            RiwayatStatusDokumen::query()->create([
                'dokumen_id' => $baris->getKey(),
                'jenis' => JenisRiwayatStatus::Berlaku,
                'dari' => $asal->value,
                'ke' => $baru->value,
                'catatan' => $catatan ?? $this->catatanOtomatis($pemicu),
                'dokumen_relasi_id' => $pemicu?->getKey(),
                'oleh' => $oleh?->getKey(),
            ]);

            $dokumen->setRawAttributes($baris->getAttributes(), true);

            StatusBerlakuBerubah::dispatch($baris, $asal, $baru, $pemicu, $oleh?->getKey());
            DB::afterCommit(fn () => CacheRegulasi::lupakanPublik());

            return $baru;
        });
    }

    /**
     * Status menurut relasi efektif yang tersisa (BR-09).
     */
    public function statusDariRelasi(Dokumen $dokumen): StatusBerlaku
    {
        $jenis = DokumenRelasi::query()
            ->where('dokumen_id', $dokumen->getKey())
            ->where('efektif', true)
            ->whereIn('jenis_relasi', array_map(fn (JenisRelasi $j): string => $j->value, JenisRelasi::berdampakStatus()))
            ->distinct()
            ->pluck('jenis_relasi')
            ->map(fn (mixed $nilai): ?JenisRelasi => $nilai instanceof JenisRelasi ? $nilai : JenisRelasi::tryFrom((string) $nilai));

        return match (true) {
            $jenis->contains(JenisRelasi::DicabutOleh) => StatusBerlaku::Dicabut,
            $jenis->contains(JenisRelasi::DiubahOleh) => StatusBerlaku::Diubah,
            default => StatusBerlaku::Berlaku,
        };
    }

    private function catatanOtomatis(?DokumenRelasi $pemicu): string
    {
        if ($pemicu === null) {
            return 'Dihitung ulang dari relasi yang berlaku.';
        }

        $pemicu->loadMissing('dokumenTerkait');

        return "Otomatis dari relasi: {$pemicu->jenis_relasi->label()} {$pemicu->dokumenTerkait->sebutan_singkat}.";
    }
}
