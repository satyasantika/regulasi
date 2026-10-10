<?php

namespace App\Actions\Relasi;

use App\Enums\JenisRiwayatStatus;
use App\Enums\StatusBerlaku;
use App\Events\StatusBerlakuBerubah;
use App\Models\Dokumen;
use App\Models\RiwayatStatusDokumen;
use App\Models\User;
use App\Support\CacheRegulasi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Status berlaku manual (BR-12): hanya admin (status-berlaku.ubah), catatan wajib.
 *
 * - `$ke = StatusBerlaku::TidakBerlaku` → tetapkan manual tidak_berlaku (status_berlaku_manual = true); hitung ulang
 *   relasi tidak menimpanya (BR-09).
 * - `$ke = StatusBerlaku::Berlaku` → "kembalikan ke perhitungan otomatis": manual = false lalu hitung ulang dari relasi.
 * - Dicabut/diubah hanya terjadi lewat relasi, tidak dapat dipilih manual.
 */
class UbahStatusBerlaku
{
    public const int MINIMUM_CATATAN = 10;

    public function __construct(
        private readonly KunciStatusDokumen $kunci,
        private readonly HitungUlangStatusBerlaku $hitungUlang,
    ) {}

    public function handle(Dokumen $dokumen, StatusBerlaku $ke, User $oleh, string $catatan): ?StatusBerlaku
    {
        Gate::forUser($oleh)->authorize('ubahStatusBerlaku', $dokumen);

        $catatan = trim($catatan);

        if (mb_strlen($catatan) < self::MINIMUM_CATATAN) {
            throw ValidationException::withMessages(['catatan' => 'Catatan perubahan status wajib diisi minimal '.self::MINIMUM_CATATAN.' karakter.']);
        }

        if (! in_array($ke, [StatusBerlaku::TidakBerlaku, StatusBerlaku::Berlaku], true)) {
            throw ValidationException::withMessages(['status_berlaku' => 'Status "'.$ke->label().'" hanya dapat terjadi lewat relasi dokumen.']);
        }

        return $this->kunci->jalankan([$dokumen->getKey()], function () use ($dokumen, $ke, $oleh, $catatan): ?StatusBerlaku {
            /** @var Dokumen $baris */
            $baris = Dokumen::withTrashed()->whereKey($dokumen->getKey())->lockForUpdate()->firstOrFail();

            $hasil = $ke === StatusBerlaku::TidakBerlaku
                ? $this->tetapkanTidakBerlaku($baris, $oleh, $catatan)
                : $this->kembalikanOtomatis($baris, $oleh, $catatan);

            $dokumen->setRawAttributes($baris->refresh()->getAttributes(), true);

            return $hasil;
        });
    }

    private function tetapkanTidakBerlaku(Dokumen $baris, User $oleh, string $catatan): StatusBerlaku
    {
        if ($baris->status_berlaku_manual && $baris->status_berlaku === StatusBerlaku::TidakBerlaku) {
            throw ValidationException::withMessages(['status_berlaku' => 'Dokumen sudah ditetapkan tidak berlaku secara manual.']);
        }

        $asal = $baris->status_berlaku;
        $baris->status_berlaku = StatusBerlaku::TidakBerlaku;
        $baris->status_berlaku_manual = true;
        $baris->save();

        RiwayatStatusDokumen::query()->create([
            'dokumen_id' => $baris->getKey(),
            'jenis' => JenisRiwayatStatus::Berlaku,
            'dari' => $asal->value,
            'ke' => StatusBerlaku::TidakBerlaku->value,
            'catatan' => $catatan,
            'oleh' => $oleh->getKey(),
        ]);

        if ($asal !== StatusBerlaku::TidakBerlaku) {
            StatusBerlakuBerubah::dispatch($baris, $asal, StatusBerlaku::TidakBerlaku, null, $oleh->getKey());
        }

        DB::afterCommit(fn () => CacheRegulasi::lupakanPublik());

        return StatusBerlaku::TidakBerlaku;
    }

    private function kembalikanOtomatis(Dokumen $baris, User $oleh, string $catatan): ?StatusBerlaku
    {
        if (! $baris->status_berlaku_manual) {
            throw ValidationException::withMessages(['status_berlaku' => 'Status berlaku dokumen sudah dihitung otomatis dari relasi.']);
        }

        $baris->status_berlaku_manual = false;
        $baris->save();

        activity('dokumen')
            ->performedOn($baris)
            ->causedBy($oleh)
            ->event('status-berlaku-otomatis')
            ->withProperties(['catatan' => $catatan])
            ->log('Status berlaku dikembalikan ke perhitungan otomatis');

        return $this->hitungUlang->handle($baris, $oleh, null, $catatan);
    }
}
