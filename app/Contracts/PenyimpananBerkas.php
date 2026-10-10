<?php

namespace App\Contracts;

use App\Enums\JenisTautan;
use App\Exceptions\Berkas\PengunduhanDitolak;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Services\Berkas\BerkasSementara;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu-satunya pintu akses berkas (02-ARSITEKTUR §4.1, STANDAR-TEKNIS §1a). Implementasi aktif
 * (BERKAS_MODE=tautan): TautanEksternal. Kelak mode disk/s3 cukup menambah implementasi baru.
 */
interface PenyimpananBerkas
{
    /**
     * @param  array{jenis: JenisTautan, url: string, label?: string|null, urutan?: int}  $data
     */
    public function simpan(Model $pemilik, array $data, User $oleh): TautanBerkas;

    /**
     * @param  array{jenis?: JenisTautan, url?: string, label?: string|null, urutan?: int}  $data
     */
    public function perbarui(TautanBerkas $tautan, array $data, User $oleh): TautanBerkas;

    public function hapus(TautanBerkas $tautan, User $oleh): void;

    /**
     * URL internal berotorisasi (/d/{id}/buka); URL mentah tidak pernah dicetak untuk pihak tak berhak.
     */
    public function urlBuka(TautanBerkas $tautan): string;

    public function urlPratinjau(TautanBerkas $tautan): ?string;

    /**
     * Salinan sementara isi berkas untuk ekstraksi teks (F7.1). Pemanggil WAJIB memanggil hapus() di finally.
     *
     * @throws PengunduhanDitolak
     */
    public function ambilSementara(TautanBerkas $tautan): BerkasSementara;
}
