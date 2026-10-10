<?php

namespace App\Actions\Dokumen;

use App\Actions\Relasi\TerapkanRelasiDokumen;
use App\Enums\StatusPublikasi;
use App\Models\Dokumen;
use App\Models\JenisDokumen;
use App\Models\User;
use App\Support\SebutanDokumen;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Membuat atau mengubah metadata dokumen (US-DOK-01). Status publikasi & berlaku tidak pernah diubah di sini
 * (hanya lewat Action F5/F6).
 *
 * - BR-03: pengguna tanpa dokumen.lihat-semua (kontributor) hanya boleh memakai kategori yang ditugaskan.
 * - BR-06: duplikat jenis + badan + nomor + tahun ditolak dengan pesan validasi.
 * - BR-19: slug dibentuk saat dibuat dan dibentuk ulang bila nomor/judul berubah.
 */
class SimpanDokumen
{
    /** Kolom metadata yang boleh diisi lewat aksi ini. */
    public const array KOLOM = [
        'jenis_dokumen_id', 'badan_penetap_id', 'nomor', 'tahun', 'judul', 'sebutan', 'sebutan_singkat',
        'tempat_penetapan', 'tanggal_penetapan', 'tanggal_pengundangan', 'tanggal_berlaku', 'sumber', 'bahasa',
        'abstrak', 'catatan', 'visibilitas',
    ];

    /** Kolom yang hanya boleh diisi admin (pemilik dokumen.lihat-semua). */
    public const array KOLOM_ADMIN = ['sebutan', 'sebutan_singkat', 'catatan'];

    public function __construct(private readonly BuatSlugDokumen $buatSlug) {}

    /**
     * @param  array<string, mixed>  $data  Kolom metadata + `kategori_sistem` (list id) + `tag` (list id).
     */
    public function handle(array $data, User $oleh, ?Dokumen $dokumen = null): Dokumen
    {
        $dokumen === null
            ? Gate::forUser($oleh)->authorize('create', Dokumen::class)
            : Gate::forUser($oleh)->authorize('update', $dokumen);

        $admin = $oleh->can('dokumen.lihat-semua');
        $kategori = array_values(array_unique(array_map('strval', (array) ($data['kategori_sistem'] ?? []))));
        $tag = array_key_exists('tag', $data) ? array_values(array_unique(array_map('strval', (array) $data['tag']))) : null;

        $this->validasi($data, $kategori, $oleh, $admin, $dokumen);

        $kolom = Arr::only($data, $admin ? self::KOLOM : array_diff(self::KOLOM, self::KOLOM_ADMIN));

        return DB::transaction(function () use ($dokumen, $kolom, $kategori, $tag, $oleh): Dokumen {
            $dokumen ??= new Dokumen(['dibuat_oleh' => $oleh->getKey()]);
            $baru = ! $dokumen->exists;

            $dokumen->fill($kolom);

            if (! $baru && $dokumen->isDirty(['jenis_dokumen_id', 'badan_penetap_id', 'nomor', 'tahun']) && ! array_key_exists('sebutan', $kolom)) {
                // Kontributor tidak menyunting sebutan: bentuk ulang dari identitas baru.
                $dokumen->sebutan = '';
                $dokumen->sebutan_singkat = '';
            }

            if (blank($dokumen->sebutan) || blank($dokumen->sebutan_singkat)) {
                $sebutan = SebutanDokumen::buat($dokumen);
                $dokumen->sebutan = blank($dokumen->sebutan) ? $sebutan['sebutan'] : $dokumen->sebutan;
                $dokumen->sebutan_singkat = blank($dokumen->sebutan_singkat) ? $sebutan['sebutan_singkat'] : $dokumen->sebutan_singkat;
            }

            if ($baru || $dokumen->isDirty(['nomor', 'judul'])) {
                $dokumen->slug = $this->buatSlug->handle($dokumen);
            }

            // Tanggal efektif dokumen terbit berubah → status relasinya sebagai sumber diterapkan ulang (BR-08).
            $tanggalBerubah = ! $baru
                && $dokumen->status_publikasi === StatusPublikasi::Terbit
                && $dokumen->isDirty(['tanggal_penetapan', 'tanggal_pengundangan', 'tanggal_berlaku']);

            $dokumen->save();

            if ($tanggalBerubah) {
                DB::afterCommit(fn () => app(TerapkanRelasiDokumen::class)->handle($dokumen, $oleh));
            }

            $perubahanKategori = $dokumen->kategoriSistem()->sync($kategori);
            $perubahanTag = $tag !== null ? $dokumen->tag()->sync($tag) : ['attached' => [], 'detached' => []];

            if (! $baru && ($perubahanKategori['attached'] !== [] || $perubahanKategori['detached'] !== [] || $perubahanTag['attached'] !== [] || $perubahanTag['detached'] !== [])) {
                activity('dokumen')
                    ->performedOn($dokumen)
                    ->causedBy($oleh)
                    ->event('klasifikasi-diubah')
                    ->withProperties([
                        'kategori' => ['ditambahkan' => $perubahanKategori['attached'], 'dicabut' => $perubahanKategori['detached']],
                        'tag' => ['ditambahkan' => $perubahanTag['attached'], 'dicabut' => $perubahanTag['detached']],
                    ])
                    ->log('Kategori/tag dokumen diubah');
            }

            return $dokumen->refresh()->load(['jenisDokumen', 'badanPenetap', 'kategoriSistem', 'tag']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $kategori
     */
    private function validasi(array $data, array $kategori, User $oleh, bool $admin, ?Dokumen $dokumen): void
    {
        $galat = [];

        if ($kategori === []) {
            $galat['kategori_sistem'] = 'Pilih minimal satu kategori sistem.';
        } elseif (! $admin) {
            $ditugaskan = $oleh->kategoriSistem()->pluck('kategori_sistem.id')->all();

            if (array_diff($kategori, $ditugaskan) !== []) {
                $galat['kategori_sistem'] = 'Anda hanya dapat memakai kategori sistem yang ditugaskan kepada Anda.';
            }
        }

        $jenisId = $data['jenis_dokumen_id'] ?? $dokumen?->jenis_dokumen_id;
        $jenis = $jenisId !== null ? JenisDokumen::withTrashed()->find($jenisId) : null;
        $nomor = array_key_exists('nomor', $data) ? $data['nomor'] : $dokumen?->nomor;

        if ($jenis?->wajib_nomor && blank($nomor)) {
            $galat['nomor'] = "Nomor wajib diisi untuk jenis {$jenis->nama}.";
        }

        $penetapan = $data['tanggal_penetapan'] ?? null;
        $pengundangan = $data['tanggal_pengundangan'] ?? null;

        if (filled($penetapan) && filled($pengundangan) && CarbonImmutable::parse((string) $pengundangan)->lt(CarbonImmutable::parse((string) $penetapan))) {
            $galat['tanggal_pengundangan'] = 'Tanggal pengundangan tidak boleh sebelum tanggal penetapan.';
        }

        if (filled($nomor) && $jenisId !== null) {
            $duplikat = Dokumen::withTrashed()
                ->where('jenis_dokumen_id', $jenisId)
                ->where('badan_penetap_id', $data['badan_penetap_id'] ?? $dokumen?->badan_penetap_id)
                ->where('nomor', $nomor)
                ->where('tahun', $data['tahun'] ?? $dokumen?->tahun)
                ->when($dokumen, fn ($q) => $q->whereKeyNot($dokumen->getKey()))
                ->exists();

            if ($duplikat) {
                $galat['nomor'] = 'Dokumen dengan jenis, badan penetap, nomor, dan tahun yang sama sudah ada (BR-06).';
            }
        }

        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }
    }
}
