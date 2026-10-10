<?php

namespace App\Actions\Relasi;

use App\Models\Dokumen;
use App\Models\DokumenRelasi;
use App\Models\User;
use App\Support\CacheRegulasi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Menghapus relasi yang keliru beserta pasangannya (US-REL-02, BR-07) lalu menghitung ulang status berlaku dokumen
 * sisi pasif (pemilik baris dicabut_oleh/diubah_oleh/dilaksanakan_oleh; BR-09). Alasan wajib dan dicatat di log
 * aktivitas.
 */
class HapusRelasiDokumen
{
    public const int MINIMUM_ALASAN = 5;

    public function __construct(
        private readonly KunciStatusDokumen $kunci,
        private readonly HitungUlangStatusBerlaku $hitungUlang,
    ) {}

    public function handle(DokumenRelasi $relasi, User $oleh, string $alasan): void
    {
        Gate::forUser($oleh)->authorize('delete', $relasi);

        $alasan = trim($alasan);

        if (mb_strlen($alasan) < self::MINIMUM_ALASAN) {
            throw ValidationException::withMessages(['alasan' => 'Alasan penghapusan relasi wajib diisi minimal '.self::MINIMUM_ALASAN.' karakter.']);
        }

        $this->kunci->jalankan([$relasi->dokumen_id, $relasi->dokumen_terkait_id], function () use ($relasi, $oleh, $alasan): void {
            Dokumen::withTrashed()->whereKey([$relasi->dokumen_id, $relasi->dokumen_terkait_id])->orderBy('id')->lockForUpdate()->get();

            $baris = DokumenRelasi::query()
                ->with(['dokumen', 'dokumenTerkait'])
                ->where(fn ($q) => $q->whereKey($relasi->getKey())
                    ->orWhere('pasangan_id', $relasi->getKey())
                    ->when($relasi->pasangan_id, fn ($q, string $pasangan) => $q->orWhereKey($pasangan)))
                ->lockForUpdate()
                ->get();

            /** @var DokumenRelasi|null $aktif */
            $aktif = $baris->first(fn (DokumenRelasi $r): bool => $r->jenis_relasi->adalahArahAktif());
            /** @var DokumenRelasi|null $pasif */
            $pasif = $baris->first(fn (DokumenRelasi $r): bool => ! $r->jenis_relasi->adalahArahAktif());

            if ($aktif === null && $pasif === null) {
                throw ValidationException::withMessages(['relasi' => 'Relasi sudah dihapus.']);
            }

            /** @var DokumenRelasi $acuan */
            $acuan = $aktif ?? $pasif;
            $sumber = $aktif !== null ? $aktif->dokumen : $acuan->dokumenTerkait;
            $target = $aktif !== null ? $aktif->dokumenTerkait : $acuan->dokumen;
            $jenis = $aktif !== null ? $aktif->jenis_relasi : $acuan->jenis_relasi->kebalikan();
            $uraian = "{$sumber->sebutan_singkat} {$jenis->label()} {$target->sebutan_singkat}";

            foreach ($baris as $sisi) {
                $sisi->delete();
            }

            activity('relasi')
                ->performedOn($sumber)
                ->causedBy($oleh)
                ->event('relasi-dihapus')
                ->withProperties([
                    'sumber_id' => $sumber->getKey(),
                    'target_id' => $target->getKey(),
                    'jenis_relasi' => $jenis->value,
                    'alasan' => $alasan,
                ])
                ->log("Relasi dihapus: {$uraian}");

            $this->hitungUlang->handle($target, $oleh, null, "Relasi dihapus ({$uraian}). Alasan: {$alasan}");

            DB::afterCommit(fn () => CacheRegulasi::lupakanPublik());
        });
    }
}
