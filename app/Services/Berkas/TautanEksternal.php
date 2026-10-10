<?php

namespace App\Services\Berkas;

use App\Contracts\PenyimpananBerkas;
use App\Enums\StatusCekTautan;
use App\Models\TautanBerkas;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Implementasi BERKAS_MODE=tautan: server hanya menyimpan URL & metadata, tanpa berkas.
 */
class TautanEksternal implements PenyimpananBerkas
{
    public function __construct(
        private readonly PenguraiTautan $pengurai,
        private readonly PengunduhAman $pengunduh,
    ) {}

    public function simpan(Model $pemilik, array $data, User $oleh): TautanBerkas
    {
        $urai = $this->pengurai->urai($data['url']);

        return TautanBerkas::create([
            'pemilik_type' => $pemilik->getMorphClass(),
            'pemilik_id' => $pemilik->getKey(),
            'jenis' => $data['jenis'],
            'label' => $data['label'] ?? null,
            'url' => $data['url'],
            'penyedia' => $urai['penyedia'],
            'drive_file_id' => $urai['drive_file_id'],
            'urutan' => $data['urutan'] ?? 0,
            'ditambahkan_oleh' => $oleh->getKey(),
        ]);
    }

    public function perbarui(TautanBerkas $tautan, array $data, User $oleh): TautanBerkas
    {
        $tautan->fill(array_intersect_key($data, array_flip(['jenis', 'label', 'url', 'urutan'])));

        if ($tautan->isDirty('url')) {
            $urai = $this->pengurai->urai($tautan->url);
            $tautan->penyedia = $urai['penyedia'];
            $tautan->drive_file_id = $urai['drive_file_id'];
            // URL berubah: hasil pemeriksaan lama tidak berlaku lagi (03-SKEMA §3.10).
            $tautan->status_cek = StatusCekTautan::Belum;
            $tautan->dicek_pada = null;
        }

        $tautan->save();

        return $tautan;
    }

    public function hapus(TautanBerkas $tautan, User $oleh): void
    {
        $tautan->delete();
    }

    public function urlBuka(TautanBerkas $tautan): string
    {
        return route('tautan.buka', ['tautanBerkas' => $tautan->getKey()]);
    }

    public function urlPratinjau(TautanBerkas $tautan): ?string
    {
        return $tautan->penyedia->dapatDipratinjau() ? $this->pengurai->urlPratinjau($tautan->drive_file_id) : null;
    }

    /**
     * Unduh sementara lewat PengunduhAman; Drive/Docs memakai URL unduh langsung (uc?export=download / export PDF).
     */
    public function ambilSementara(TautanBerkas $tautan): BerkasSementara
    {
        return $this->pengunduh->unduh($this->pengurai->urlUnduh($tautan->url));
    }
}
