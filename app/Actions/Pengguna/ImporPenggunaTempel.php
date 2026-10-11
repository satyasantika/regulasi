<?php

namespace App\Actions\Pengguna;

use App\Enums\Peran;
use App\Models\KategoriSistem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use SensitiveParameter;

/**
 * Impor massal pengguna dari teks salin-tempel Excel/Google Sheets (hanya super-admin).
 *
 * Kolom berurutan: nama, surel, peran[, kategori]. Kategori (kode/nama/slug kategori sistem; beberapa dipisah
 * "|", koma, atau titik koma) hanya untuk kontributor. Pemisah kolom otomatis: tab, titik koma, atau koma;
 * baris judul dilewati. Surel wajib berdomain config('regulasi.domain_registrasi').
 *
 * Idempoten: surel yang sudah terdaftar dilewati (tidak diubah), sehingga impor ulang data yang sama tidak membuat
 * akun ganda. Semua-atau-tidak-sama-sekali: satu baris salah membatalkan seluruh impor. `periksa()` dipakai untuk
 * pratinjau tanpa menyimpan apa pun.
 */
class ImporPenggunaTempel
{
    public const int MAKS_BARIS = 500;

    public function __construct(
        private AturPenugasanKategori $aturPenugasan,
        private KirimTautanAturSandi $kirimTautan,
    ) {}

    /**
     * Pratinjau: hasil urai dan validasi tanpa menulis ke basis data.
     *
     * @return array{baru: list<array{baris: int, name: string, email: string, peran: Peran, kategori: array<string, string>}>, dilewati: list<string>, galat: list<string>}
     */
    public function periksa(string $teks): array
    {
        [$baris, $galat] = $this->urai($teks);
        $baru = [];
        $dilewati = [];

        if ($baris === [] && $galat === []) {
            return ['baru' => [], 'dilewati' => [], 'galat' => ['Tidak ada baris data untuk diimpor.']];
        }

        if (count($baris) > self::MAKS_BARIS) {
            return ['baru' => [], 'dilewati' => [], 'galat' => ['Maksimal '.self::MAKS_BARIS.' baris per impor.']];
        }

        $domain = mb_strtolower((string) config('regulasi.domain_registrasi'));
        $sudahAda = User::query()->pluck('email')->map(fn (string $e): string => mb_strtolower($e))->flip();
        $kategoriAktif = KategoriSistem::query()->aktif()->get(['id', 'kode', 'nama', 'slug']);
        $terlihat = [];

        foreach ($baris as $nomor => [$nama, $surel, $peranTeks, $kategoriTeks]) {
            $surel = mb_strtolower($surel);
            $pesan = [];

            $validator = Validator::make(
                ['nama' => $nama, 'surel' => $surel],
                ['nama' => ['required', 'max:255'], 'surel' => ['required', 'email', 'max:255']],
                [],
                ['nama' => 'nama', 'surel' => 'surel'],
            );
            array_push($pesan, ...$validator->errors()->all());

            if ($surel !== '' && ! str_ends_with($surel, '@'.$domain)) {
                $pesan[] = "surel harus berdomain @{$domain}";
            }

            if (isset($terlihat[$surel])) {
                $pesan[] = "surel {$surel} ganda dalam data tempel (baris {$terlihat[$surel]})";
            }
            $terlihat[$surel] ??= $nomor;

            $peran = $this->peran($peranTeks);

            if ($peran === null) {
                $pesan[] = "peran '{$peranTeks}' tidak dikenal (pakai: ".implode(', ', Peran::nilai(Peran::cases())).')';
            }

            $kategori = [];

            if ($kategoriTeks !== '') {
                if ($peran !== null && $peran !== Peran::Kontributor) {
                    $pesan[] = 'kategori hanya untuk peran kontributor';
                }

                foreach (array_filter(array_map('trim', preg_split('/[|,;]/', $kategoriTeks) ?: [])) as $kunci) {
                    $cocok = $kategoriAktif->first(fn (KategoriSistem $k): bool => in_array(mb_strtolower($kunci), [mb_strtolower($k->kode), mb_strtolower($k->nama), mb_strtolower($k->slug)], true));

                    if ($cocok === null) {
                        $pesan[] = "kategori '{$kunci}' tidak ditemukan atau nonaktif";
                    } else {
                        $kategori[$cocok->id] = $cocok->kode;
                    }
                }
            }

            if ($pesan !== []) {
                $galat[] = "Baris {$nomor}: ".implode('; ', $pesan).'.';

                continue;
            }

            if ($sudahAda->has($surel)) {
                $dilewati[] = $surel;

                continue;
            }

            /** @var Peran $peran */
            $baru[] = ['baris' => $nomor, 'name' => $nama, 'email' => $surel, 'peran' => $peran, 'kategori' => $kategori];
        }

        return ['baru' => $galat === [] ? $baru : [], 'dilewati' => $dilewati, 'galat' => $galat];
    }

    /**
     * Menyimpan akun baru. Tanpa kata sandi awal: sandi acak dan tautan atur kata sandi dikirim ke tiap akun.
     *
     * @return array{dibuat: int, dilewati: list<string>, galat: list<string>}
     */
    public function handle(string $teks, User $oleh, #[SensitiveParameter] ?string $kataSandiAwal = null, bool $wajibGantiSandi = true): array
    {
        if (! $oleh->hasRole(Peran::SuperAdmin->value)) {
            throw new AuthorizationException('Hanya super-admin yang boleh mengimpor pengguna.');
        }

        $hasil = $this->periksa($teks);

        if ($hasil['galat'] !== []) {
            return ['dibuat' => 0, 'dilewati' => $hasil['dilewati'], 'galat' => $hasil['galat']];
        }

        if (filled($kataSandiAwal) && mb_strlen((string) $kataSandiAwal) < 8) {
            return ['dibuat' => 0, 'dilewati' => $hasil['dilewati'], 'galat' => ['Kata sandi awal minimal 8 karakter.']];
        }

        $dibuat = DB::transaction(function () use ($hasil, $oleh, $kataSandiAwal, $wajibGantiSandi): array {
            $dibuat = [];

            foreach ($hasil['baru'] as $data) {
                $akun = new User([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'aktif' => true,
                    'password' => filled($kataSandiAwal) ? (string) $kataSandiAwal : Str::password(48),
                ]);
                $akun->forceFill([
                    'email_verified_at' => now(),
                    'wajib_ganti_sandi' => filled($kataSandiAwal) && $wajibGantiSandi,
                ])->save();
                $akun->assignRole($data['peran']->value);

                if ($data['kategori'] !== []) {
                    $this->aturPenugasan->handle($akun, array_map('strval', array_keys($data['kategori'])), $oleh);
                }

                $dibuat[] = $akun;
            }

            activity('pengguna')
                ->causedBy($oleh)
                ->event('impor-pengguna')
                ->withProperties([
                    'dibuat' => array_map(fn (User $u): string => $u->email, $dibuat),
                    'dilewati' => $hasil['dilewati'],
                    'sandi_awal' => filled($kataSandiAwal) ? 'ditetapkan' : 'tautan',
                ])
                ->log('Impor massal pengguna: '.count($dibuat).' dibuat, '.count($hasil['dilewati']).' dilewati');

            return $dibuat;
        });

        if (blank($kataSandiAwal)) {
            DB::afterCommit(fn () => collect($dibuat)->each(fn (User $u) => $this->kirimTautan->handle($u)));
        }

        return ['dibuat' => count($dibuat), 'dilewati' => $hasil['dilewati'], 'galat' => []];
    }

    /**
     * @return array{0: array<int, array{0: string, 1: string, 2: string, 3: string}>, 1: list<string>}
     */
    private function urai(string $teks): array
    {
        $hasil = [];
        $galat = [];

        foreach (preg_split('/\R/u', trim($teks)) ?: [] as $i => $baris) {
            $nomor = $i + 1;

            if (trim($baris) === '') {
                continue;
            }

            $pemisah = str_contains($baris, "\t") ? "\t" : (str_contains($baris, ';') ? ';' : ',');
            $kolom = array_map(fn (?string $k): string => trim((string) $k), str_getcsv($baris, $pemisah, '"', ''));

            // Baris judul (kolom surel tanpa @) di baris pertama dilewati.
            if ($hasil === [] && $galat === [] && ! str_contains($kolom[1] ?? '', '@')) {
                continue;
            }

            if (count($kolom) < 3) {
                $galat[] = "Baris {$nomor}: minimal berisi nama, surel, dan peran.";

                continue;
            }

            [$nama, $surel, $peran] = $kolom;
            // Kolom ke-4 dst. = kategori (bila dipisah koma, kategori kedua dst. jatuh ke kolom berikutnya).
            $kategori = implode('|', array_filter(array_slice($kolom, 3), fn (string $k): bool => $k !== ''));

            $hasil[$nomor] = [$nama, $surel, $peran, $kategori];
        }

        return [$hasil, $galat];
    }

    private function peran(string $teks): ?Peran
    {
        $kunci = mb_strtolower(trim($teks));

        foreach (Peran::cases() as $peran) {
            if ($kunci === $peran->value || $kunci === mb_strtolower($peran->getLabel()) || $kunci === str_replace('-', ' ', $peran->value)) {
                return $peran;
            }
        }

        return null;
    }
}
