<?php

namespace App\Console\Commands;

use App\Enums\StatusEkstraksi;
use App\Jobs\EkstrakTeksTautan;
use App\Models\EkstraksiTeks;
use App\Models\TautanBerkas;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Antrekan ulang ekstraksi teks (02-ARSITEKTUR §9): --gagal (percobaan < batas), --tanpa-teks, atau tautan tertentu.
 * Status tidak_dapat_diunduh/terlalu_besar/bukan_pdf tidak diulang otomatis; pakai --tautan setelah diperbaiki.
 */
#[Signature('regulasi:ekstraksi-ulang
    {--gagal : Ekstraksi berstatus gagal dengan percobaan di bawah batas}
    {--tanpa-teks : Ekstraksi berstatus tanpa_teks}
    {--tautan=* : ID tautan berkas tertentu}')]
#[Description('Antrekan ulang ekstraksi teks PDF dari tautan berkas')]
class EkstraksiUlangCommand extends Command
{
    public function handle(): int
    {
        $tautan = array_values(array_filter((array) $this->option('tautan'), 'filled'));

        if (! $this->option('gagal') && ! $this->option('tanpa-teks') && $tautan === []) {
            $this->error('Pilih minimal satu: --gagal, --tanpa-teks, atau --tautan=ID.');

            return self::INVALID;
        }

        $id = collect($tautan);

        if ($this->option('gagal') || $this->option('tanpa-teks')) {
            $id = $id->merge(
                EkstraksiTeks::query()
                    ->whereHas('tautanBerkas', fn (Builder $q) => $q->withoutTrashed())
                    ->where(function (Builder $q): void {
                        if ($this->option('gagal')) {
                            $q->orWhere(fn (Builder $q) => $q->where('status', StatusEkstraksi::Gagal->value)
                                ->where('percobaan', '<', (int) config('regulasi.maks_percobaan_ekstraksi', 5)));
                        }

                        if ($this->option('tanpa-teks')) {
                            $q->orWhere('status', StatusEkstraksi::TanpaTeks->value);
                        }
                    })
                    ->pluck('tautan_berkas_id'),
            );
        }

        $id = TautanBerkas::query()->whereKey($id->unique()->values()->all())->pluck('id');

        foreach ($id as $tautanId) {
            EkstrakTeksTautan::dispatch((string) $tautanId);
        }

        $this->info("{$id->count()} ekstraksi diantrekan ulang.");

        return self::SUCCESS;
    }
}
