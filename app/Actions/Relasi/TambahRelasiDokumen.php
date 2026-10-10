<?php

namespace App\Actions\Relasi;

use App\Enums\JenisRelasi;
use App\Models\Dokumen;
use App\Models\DokumenRelasi;
use App\Models\User;
use App\Support\CacheRegulasi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Menambah relasi simetris antardokumen (US-REL-01, BR-07, BR-08, BR-10; 02-ARSITEKTUR §13.1).
 *
 * - Jenis pasif (dicabut_oleh/diubah_oleh/dilaksanakan_oleh) dari sisi target dinormalkan: sumber/target ditukar
 *   dan jenis diganti kebalikannya, sehingga data yang tersimpan identik dari sisi mana pun dicatat.
 * - Kunci `lock:status-dokumen:{id}` kedua dokumen (id menaik) + DB::transaction + lockForUpdate.
 * - Dua baris saling `pasangan_id`; efektif bila sumber terbit dan tanggal efektifnya sudah tiba (BR-08), lalu
 *   status target dihitung ulang (BR-09).
 * - Satu-satunya tempat yang menyisipkan baris dokumen_relasi.
 */
class TambahRelasiDokumen
{
    public const int MAKS_KETERANGAN = 500;

    public function __construct(
        private readonly KunciStatusDokumen $kunci,
        private readonly HitungUlangStatusBerlaku $hitungUlang,
    ) {}

    /**
     * @return DokumenRelasi Baris arah aktif (milik dokumen sumber).
     */
    public function handle(Dokumen $sumber, Dokumen $target, JenisRelasi $jenis, ?string $keterangan, User $oleh): DokumenRelasi
    {
        Gate::forUser($oleh)->authorize('create', DokumenRelasi::class);

        if (! $jenis->adalahArahAktif()) {
            [$sumber, $target, $jenis] = [$target, $sumber, $jenis->kebalikan()];
        }

        $keterangan = filled($keterangan) ? trim((string) $keterangan) : null;

        if ($sumber->getKey() === $target->getKey()) {
            throw ValidationException::withMessages(['dokumen_terkait_id' => 'Dokumen tidak dapat berelasi dengan dirinya sendiri (BR-10).']);
        }

        if ($keterangan !== null && mb_strlen($keterangan) > self::MAKS_KETERANGAN) {
            throw ValidationException::withMessages(['keterangan' => 'Keterangan maksimal '.self::MAKS_KETERANGAN.' karakter.']);
        }

        /** @var DokumenRelasi */
        return $this->kunci->jalankan([$sumber->getKey(), $target->getKey()], function () use ($sumber, $target, $jenis, $keterangan, $oleh): DokumenRelasi {
            $baris = Dokumen::query()
                ->whereKey([$sumber->getKey(), $target->getKey()])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var Dokumen|null $barisSumber */
            $barisSumber = $baris->get($sumber->getKey());
            /** @var Dokumen|null $barisTarget */
            $barisTarget = $baris->get($target->getKey());

            if ($barisSumber === null || $barisTarget === null) {
                throw ValidationException::withMessages(['dokumen_terkait_id' => 'Dokumen tidak ditemukan.']);
            }

            $this->validasi($barisSumber, $barisTarget, $jenis);

            $efektif = $barisSumber->efektifSebagaiSumber();
            $efektifPada = $barisSumber->tanggal_efektif?->toDateString();

            $isian = [
                'efektif' => $efektif,
                'efektif_pada' => $efektifPada,
                'keterangan' => $keterangan,
                'dibuat_oleh' => $oleh->getKey(),
            ];

            $aktif = DokumenRelasi::query()->create([
                'dokumen_id' => $barisSumber->getKey(),
                'dokumen_terkait_id' => $barisTarget->getKey(),
                'jenis_relasi' => $jenis,
                ...$isian,
            ]);

            $pasif = DokumenRelasi::query()->create([
                'dokumen_id' => $barisTarget->getKey(),
                'dokumen_terkait_id' => $barisSumber->getKey(),
                'jenis_relasi' => $jenis->kebalikan(),
                'pasangan_id' => $aktif->getKey(),
                ...$isian,
            ]);

            $aktif->pasangan_id = $pasif->getKey();
            $aktif->save();

            if ($efektif) {
                $this->hitungUlang->handle($barisTarget, $oleh, $pasif);
            }

            DB::afterCommit(fn () => CacheRegulasi::lupakanPublik());

            return $aktif;
        });
    }

    /**
     * BR-10: tidak ganda, tidak siklik untuk mencabut, tanggal penetapan sumber ≥ target.
     */
    private function validasi(Dokumen $sumber, Dokumen $target, JenisRelasi $jenis): void
    {
        $ganda = DokumenRelasi::query()
            ->where('dokumen_id', $sumber->getKey())
            ->where('dokumen_terkait_id', $target->getKey())
            ->where('jenis_relasi', $jenis->value)
            ->exists();

        if ($ganda) {
            throw ValidationException::withMessages([
                'dokumen_terkait_id' => "Relasi \"{$sumber->sebutan_singkat} {$jenis->label()} {$target->sebutan_singkat}\" sudah ada (BR-10).",
            ]);
        }

        if ($jenis === JenisRelasi::Mencabut) {
            $siklik = DokumenRelasi::query()
                ->where('dokumen_id', $target->getKey())
                ->where('dokumen_terkait_id', $sumber->getKey())
                ->where('jenis_relasi', JenisRelasi::Mencabut->value)
                ->exists();

            if ($siklik) {
                throw ValidationException::withMessages([
                    'dokumen_terkait_id' => "{$target->sebutan_singkat} sudah tercatat mencabut {$sumber->sebutan_singkat}; relasi siklik tidak diizinkan (BR-10).",
                ]);
            }
        }

        if (in_array($jenis, [JenisRelasi::Mencabut, JenisRelasi::Mengubah], true)
            && $sumber->tanggal_penetapan !== null
            && $target->tanggal_penetapan !== null
            && $sumber->tanggal_penetapan->lt($target->tanggal_penetapan)) {
            throw ValidationException::withMessages([
                'dokumen_terkait_id' => "Tanggal penetapan {$sumber->sebutan_singkat} lebih awal daripada {$target->sebutan_singkat}; dokumen yang lebih lama tidak dapat {$jenis->label()} dokumen yang lebih baru (BR-10).",
            ]);
        }
    }
}
