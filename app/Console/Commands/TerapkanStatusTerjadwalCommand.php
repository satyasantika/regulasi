<?php

namespace App\Console\Commands;

use App\Actions\Relasi\TerapkanRelasiDokumen;
use App\Enums\JenisRelasi;
use App\Enums\StatusPublikasi;
use App\Models\DokumenRelasi;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Menerapkan relasi tertunda (US-REL-03, BR-08): relasi arah aktif yang belum efektif, sumbernya terbit, dan
 * tanggal efektifnya sudah tiba. Dijadwalkan harian di F10.2.
 */
#[Signature('regulasi:terapkan-status {--dry-run : Tampilkan relasi tertunda tanpa mengubah data}')]
#[Description('Terapkan relasi dokumen yang tanggal efektifnya sudah tiba (status berlaku target dihitung ulang)')]
class TerapkanStatusTerjadwalCommand extends Command
{
    public const string KUNCI = 'lock:terapkan-status-terjadwal';

    public function handle(TerapkanRelasiDokumen $terapkan): int
    {
        $tertunda = $this->relasiTertunda();

        if ($this->option('dry-run')) {
            $this->table(
                ['Sumber', 'Relasi', 'Target', 'Efektif pada'],
                $tertunda->map(fn (DokumenRelasi $r): array => [
                    $r->dokumen->sebutan_singkat,
                    $r->jenis_relasi->label(),
                    $r->dokumenTerkait->sebutan_singkat,
                    $r->efektif_pada?->translatedFormat('d F Y') ?? '—',
                ])->all(),
            );
            $this->info("{$tertunda->count()} relasi tertunda siap diterapkan (dry-run, tidak ada perubahan).");

            return self::SUCCESS;
        }

        $kunci = Cache::lock(self::KUNCI, 600);

        if (! $kunci->get()) {
            $this->warn('Penerapan status lain sedang berjalan; dilewati.');

            return self::SUCCESS;
        }

        try {
            $berubah = 0;

            foreach ($tertunda->groupBy('dokumen_id') as $relasi) {
                $berubah += $terapkan->handle($relasi->first()->dokumen);
            }
        } finally {
            $kunci->release();
        }

        $this->info("{$berubah} relasi diterapkan dari {$tertunda->count()} relasi tertunda.");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, DokumenRelasi>
     */
    private function relasiTertunda(): Collection
    {
        return DokumenRelasi::query()
            ->with(['dokumen', 'dokumenTerkait'])
            ->whereIn('jenis_relasi', array_map(fn (JenisRelasi $j): string => $j->value, JenisRelasi::arahAktif()))
            ->where('efektif', false)
            ->where(fn (Builder $q) => $q->whereNull('efektif_pada')->orWhereDate('efektif_pada', '<=', today()))
            ->whereHas('dokumen', fn (Builder $q) => $q->where('status_publikasi', StatusPublikasi::Terbit->value))
            ->orderBy('efektif_pada')
            ->get()
            ->toBase();
    }
}
