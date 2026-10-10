<?php

namespace App\Actions\Berkas;

use App\Contracts\PenyimpananBerkas;
use App\Enums\JenisTautan;
use App\Enums\StatusEkstraksi;
use App\Jobs\EkstrakTeksTautan;
use App\Jobs\PeriksaTautanBerkas;
use App\Models\Dokumen;
use App\Models\EkstraksiTeks;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Rules\TautanBerkasValid;
use App\Services\Berkas\PenguraiTautan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Menambah atau mengubah tautan berkas dokumen (US-DOK-02, BR-13), tanpa unggahan.
 *
 * - URL divalidasi TautanBerkasValid; penyedia & drive_file_id diisi otomatis.
 * - URL/drive_file_id yang sama pada dokumen yang sama ditolak; sama dengan dokumen lain → peringatan.
 * - Naskah utama baru menurunkan naskah utama aktif sebelumnya menjadi lampiran (dicatat activitylog).
 * - Setelah commit, PeriksaTautanBerkas diantrekan (dibuat/URL berubah).
 * - Ekstraksi teks (F7.1): dibuat/URL berubah → ekstraksi direset dan EkstrakTeksTautan diantrekan bila jenisnya
 *   diekstraksi; jenis halaman_sumber/lainnya → status tidak_berlaku tanpa job.
 */
class SimpanTautanBerkas
{
    public function __construct(
        private readonly PenyimpananBerkas $penyimpanan,
        private readonly PenguraiTautan $pengurai,
    ) {}

    /**
     * @param  array<string, mixed>  $data  url, jenis, label, urutan
     * @return array{tautan: TautanBerkas, peringatan: string|null}
     */
    public function handle(Dokumen $dokumen, array $data, User $oleh, ?TautanBerkas $tautan = null): array
    {
        $tautan === null
            ? Gate::forUser($oleh)->authorize('create', [TautanBerkas::class, $dokumen])
            : Gate::forUser($oleh)->authorize('update', $tautan);

        $isian = $this->validasi($data, $tautan);

        return DB::transaction(function () use ($dokumen, $isian, $oleh, $tautan): array {
            // Serialkan perubahan tautan pada dokumen yang sama (cek ganda & naskah utama tunggal).
            Dokumen::withTrashed()->whereKey($dokumen->getKey())->lockForUpdate()->first();

            $driveFileId = $this->pengurai->driveFileId($isian['url']);

            $this->tolakGanda($dokumen, $isian['url'], $driveFileId, $tautan);
            $peringatan = $this->peringatanDokumenLain($dokumen, $isian['url'], $driveFileId);

            if ($isian['jenis'] === JenisTautan::NaskahUtama) {
                $this->turunkanNaskahUtamaLama($dokumen, $oleh, $tautan);
            }

            $urlLama = $tautan?->url;
            $diekstraksiLama = $tautan?->jenis->diekstraksi();

            $hasil = $tautan === null
                ? $this->penyimpanan->simpan($dokumen, $isian, $oleh)
                : $this->penyimpanan->perbarui($tautan, $isian, $oleh);

            $urlBerubah = $tautan === null || $urlLama !== $hasil->url;

            if ($urlBerubah) {
                PeriksaTautanBerkas::dispatch($hasil)->afterCommit();
            }

            if ($urlBerubah || $diekstraksiLama !== $hasil->jenis->diekstraksi()) {
                $this->siapkanEkstraksi($hasil);
            }

            return ['tautan' => $hasil, 'peringatan' => $peringatan];
        });
    }

    /**
     * Teks lama tidak berlaku lagi untuk URL/jenis baru (03-SKEMA §3.10): reset lalu antrekan ekstraksi.
     */
    private function siapkanEkstraksi(TautanBerkas $tautan): void
    {
        $ekstraksi = EkstraksiTeks::query()->firstOrNew(['tautan_berkas_id' => $tautan->getKey()]);

        if (! $tautan->jenis->diekstraksi()) {
            $ekstraksi->setelUlang(StatusEkstraksi::TidakBerlaku);
            $ekstraksi->save();

            return;
        }

        $ekstraksi->setelUlang();
        $ekstraksi->save();

        EkstrakTeksTautan::dispatch($tautan->getKey())->afterCommit();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{jenis: JenisTautan, url: string, label: string|null, urutan: int}
     */
    private function validasi(array $data, ?TautanBerkas $tautan): array
    {
        $jenisMentah = $data['jenis'] ?? $tautan->jenis ?? JenisTautan::Lampiran;
        $jenis = $jenisMentah instanceof JenisTautan ? $jenisMentah : JenisTautan::tryFrom((string) $jenisMentah);
        $url = trim((string) ($data['url'] ?? $tautan->url ?? ''));
        $label = array_key_exists('label', $data) ? $data['label'] : $tautan?->label;
        $label = filled($label) ? trim((string) $label) : null;
        $urutan = (int) ($data['urutan'] ?? $tautan->urutan ?? 0);

        $galat = [];

        if ($jenis === null) {
            $galat['jenis'] = 'Jenis tautan tidak dikenal.';
        }

        $pesanUrl = TautanBerkasValid::periksa($url, $jenis);

        if ($pesanUrl !== null) {
            $galat['url'] = $pesanUrl;
        }

        if ($label !== null && mb_strlen($label) > 255) {
            $galat['label'] = 'Label maksimal 255 karakter.';
        }

        if ($urutan < 0 || $urutan > 65535) {
            $galat['urutan'] = 'Urutan harus antara 0 dan 65535.';
        }

        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }

        /** @var JenisTautan $jenis */
        return ['jenis' => $jenis, 'url' => $url, 'label' => $label, 'urutan' => $urutan];
    }

    private function tolakGanda(Dokumen $dokumen, string $url, ?string $driveFileId, ?TautanBerkas $tautan): void
    {
        $ganda = TautanBerkas::query()
            ->where('pemilik_type', $dokumen->getMorphClass())
            ->where('pemilik_id', $dokumen->getKey())
            ->when($tautan, fn ($q) => $q->whereKeyNot($tautan->getKey()))
            ->where(fn ($q) => $q->where('url', $url)->when($driveFileId, fn ($q) => $q->orWhere('drive_file_id', $driveFileId)))
            ->exists();

        if ($ganda) {
            throw ValidationException::withMessages([
                'url' => 'Tautan atau berkas Drive yang sama sudah ada pada dokumen ini. Ubah tautan yang ada alih-alih menambah baru.',
            ]);
        }
    }

    private function peringatanDokumenLain(Dokumen $dokumen, string $url, ?string $driveFileId): ?string
    {
        $lain = TautanBerkas::query()
            ->where('pemilik_type', $dokumen->getMorphClass())
            ->where('pemilik_id', '!=', $dokumen->getKey())
            ->where(fn ($q) => $q->where('url', $url)->when($driveFileId, fn ($q) => $q->orWhere('drive_file_id', $driveFileId)))
            ->pluck('pemilik_id')
            ->unique();

        if ($lain->isEmpty()) {
            return null;
        }

        $sebutan = Dokumen::withTrashed()->whereKey($lain->all())->orderBy('sebutan_singkat')->limit(3)->pluck('sebutan_singkat')->implode(', ');

        return "Tautan yang sama juga dipakai dokumen lain ({$sebutan}). Pastikan ini memang berkas yang benar.";
    }

    private function turunkanNaskahUtamaLama(Dokumen $dokumen, User $oleh, ?TautanBerkas $kecuali): void
    {
        $lama = TautanBerkas::query()
            ->where('pemilik_type', $dokumen->getMorphClass())
            ->where('pemilik_id', $dokumen->getKey())
            ->where('jenis', JenisTautan::NaskahUtama->value)
            ->when($kecuali, fn ($q) => $q->whereKeyNot($kecuali->getKey()))
            ->get();

        foreach ($lama as $naskah) {
            $naskah->jenis = JenisTautan::Lampiran;
            $naskah->save();

            activity('tautan')
                ->performedOn($naskah)
                ->causedBy($oleh)
                ->event('naskah-utama-diganti')
                ->withProperties(['dokumen_id' => $dokumen->getKey(), 'url' => $naskah->url])
                ->log('Naskah utama lama diturunkan menjadi lampiran karena naskah utama baru ditambahkan');
        }
    }
}
