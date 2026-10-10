<?php

namespace App\Actions\Relasi;

use App\Actions\Dokumen\SimpanDokumen;
use App\Actions\Dokumen\TerbitkanDokumen;
use App\Models\Dokumen;
use App\Models\DokumenRelasi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Dokumen rujukan (BR-11): metadata saja (`tanpa_berkas = true`) untuk peraturan yang belum ada di repositori,
 * dibuat lewat SimpanDokumen lalu langsung diterbitkan lewat TerbitkanDokumen (dikecualikan dari syarat tautan
 * naskah utama & tanggal penetapan BR-05).
 */
class BuatDokumenRujukan
{
    public const string CATATAN_TERBIT = 'Dokumen rujukan (metadata saja, BR-11).';

    public function __construct(
        private readonly SimpanDokumen $simpan,
        private readonly TerbitkanDokumen $terbitkan,
    ) {}

    /**
     * @param  array<string, mixed>  $data  jenis_dokumen_id, nomor, tahun, judul, badan_penetap_id, kategori_sistem, …
     */
    public function handle(array $data, User $oleh): Dokumen
    {
        Gate::forUser($oleh)->authorize('create', DokumenRelasi::class);

        $dokumen = DB::transaction(function () use ($data, $oleh): Dokumen {
            $dokumen = $this->simpan->handle($data, $oleh);
            $dokumen->tanpa_berkas = true;
            $dokumen->save();

            return $dokumen;
        });

        return $this->terbitkan->handle($dokumen, $oleh, self::CATATAN_TERBIT);
    }
}
