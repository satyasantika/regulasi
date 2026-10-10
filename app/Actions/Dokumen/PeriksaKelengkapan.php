<?php

namespace App\Actions\Dokumen;

use App\Models\Dokumen;
use App\Models\JenisDokumen;

/**
 * Pemeriksaan syarat terbit BR-05: jenis, judul, tahun, badan penetap, minimal satu kategori sistem,
 * tautan naskah utama (kecuali dokumen rujukan `tanpa_berkas`), serta nomor & tanggal penetapan untuk jenis
 * wajib nomor (dokumen rujukan dikecualikan dari tanggal penetapan).
 *
 * Relasi yang sudah di-eager load (jenisDokumen, kategoriSistem, naskahUtama) dipakai agar tabel tanpa N+1.
 */
class PeriksaKelengkapan
{
    /**
     * Daftar kekurangan BR-05 dalam bahasa Indonesia; kosong berarti lengkap.
     *
     * @return list<string>
     */
    public function handle(Dokumen $dokumen): array
    {
        return array_values(array_map(
            fn (array $butir): string => $butir['pesan'],
            array_filter($this->daftar($dokumen), fn (array $butir): bool => ! $butir['terpenuhi']),
        ));
    }

    public function lengkap(Dokumen $dokumen): bool
    {
        return $this->handle($dokumen) === [];
    }

    /**
     * Butir syarat yang berlaku untuk dokumen ini beserta status terpenuhinya.
     *
     * @return list<array{kunci: string, label: string, terpenuhi: bool, pesan: string}>
     */
    public function daftar(Dokumen $dokumen): array
    {
        $jenis = $this->jenis($dokumen);
        $wajibNomor = $jenis !== null && $jenis->wajib_nomor;

        $butir = [
            $this->butir('jenis', 'Jenis dokumen', $jenis !== null, 'Jenis dokumen belum dipilih.'),
            $this->butir('judul', 'Judul (tentang)', filled($dokumen->judul), 'Judul (tentang) belum diisi.'),
            $this->butir('tahun', 'Tahun', filled($dokumen->tahun), 'Tahun belum diisi.'),
            $this->butir('badan', 'Badan penetap (T.E.U.)', filled($dokumen->badan_penetap_id), 'Badan penetap belum dipilih.'),
            $this->butir('kategori', 'Minimal satu kategori sistem', $this->adaKategori($dokumen), 'Pilih minimal satu kategori sistem.'),
        ];

        if (! $dokumen->tanpa_berkas) {
            $butir[] = $this->butir('naskah_utama', 'Tautan naskah utama', $this->adaNaskahUtama($dokumen), 'Tambahkan tautan berkas berjenis "Naskah Utama".');
        }

        if ($wajibNomor) {
            $butir[] = $this->butir('nomor', 'Nomor', filled($dokumen->nomor), "Nomor wajib diisi untuk jenis {$jenis->nama}.");

            if (! $dokumen->tanpa_berkas) {
                $butir[] = $this->butir('tanggal_penetapan', 'Tanggal penetapan', $dokumen->tanggal_penetapan !== null, "Tanggal penetapan wajib diisi untuk jenis {$jenis->nama}.");
            }
        }

        return $butir;
    }

    /**
     * @return array{kunci: string, label: string, terpenuhi: bool, pesan: string}
     */
    private function butir(string $kunci, string $label, bool $terpenuhi, string $pesan): array
    {
        return ['kunci' => $kunci, 'label' => $label, 'terpenuhi' => $terpenuhi, 'pesan' => $pesan];
    }

    private function jenis(Dokumen $dokumen): ?JenisDokumen
    {
        if (blank($dokumen->jenis_dokumen_id)) {
            return null;
        }

        if ($dokumen->relationLoaded('jenisDokumen')) {
            return $dokumen->jenisDokumen;
        }

        return JenisDokumen::withTrashed()->find($dokumen->jenis_dokumen_id);
    }

    private function adaKategori(Dokumen $dokumen): bool
    {
        if ($dokumen->relationLoaded('kategoriSistem')) {
            return $dokumen->kategoriSistem->isNotEmpty();
        }

        return $dokumen->exists && $dokumen->kategoriSistem()->exists();
    }

    private function adaNaskahUtama(Dokumen $dokumen): bool
    {
        if ($dokumen->relationLoaded('naskahUtama')) {
            return $dokumen->naskahUtama !== null;
        }

        return $dokumen->exists && $dokumen->naskahUtama()->exists();
    }
}
