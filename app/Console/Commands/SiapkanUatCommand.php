<?php

namespace App\Console\Commands;

use App\Actions\Api\BuatTokenKlienApi;
use App\Enums\Peran;
use App\Models\KategoriSistem;
use App\Models\KlienApi;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Menyiapkan data UAT (05-UJI-PENERIMAAN §1, F12.5): seed lengkap (peran, master, super-admin dari env, dokumen
 * contoh) lalu akun uji per peran §1.2 dan dua klien API uji bertoken. Hanya di luar produksi. Idempoten: akun uji
 * yang sudah ada diperbarui (peran, penugasan, kata sandi), token UAT lama dicabut lalu dibuat ulang.
 *
 * Akun admin-regulasi & super-admin uji TIDAK diberi MFA agar skenario A-01 (MFA wajib saat masuk pertama) dapat diuji.
 */
#[Signature('regulasi:siapkan-uat
    {--segar : Kosongkan basis data (migrate:fresh) sebelum seed}
    {--sandi= : Kata sandi semua akun uji (bawaan UAT_PASSWORD, atau acak dan ditampilkan sekali)}
    {--tanpa-seed : Lewati seed master & dokumen contoh (peran tetap disinkronkan), hanya akun & klien API uji}')]
#[Description('Siapkan data & akun UAT per peran (hanya non-produksi)')]
class SiapkanUatCommand extends Command
{
    /**
     * Akun uji 05-UJI §1.2: surel => [nama, peran, kode kategori yang ditugaskan].
     *
     * @var array<string, array{0: string, 1: Peran, 2: list<string>}>
     */
    public const array AKUN = [
        'uat.superadmin@unsil.ac.id' => ['UAT Super Admin', Peran::SuperAdmin, []],
        'uat.admin@unsil.ac.id' => ['UAT Admin Regulasi', Peran::AdminRegulasi, []],
        'uat.aset@unsil.ac.id' => ['UAT Kontributor Aset', Peran::Kontributor, ['aset']],
        'uat.persuratan@unsil.ac.id' => ['UAT Kontributor Persuratan', Peran::Kontributor, ['persuratan']],
        'uat.pengguna@unsil.ac.id' => ['UAT Pengguna', Peran::Pengguna, []],
    ];

    /**
     * Klien API uji: nama klien => [nama token, boleh baca internal, kode kategori klien].
     *
     * @var array<string, array{0: string, 1: bool, 2: string|null}>
     */
    public const array KLIEN = [
        'UAT Sistem Aset' => ['UAT Sistem Aset', false, 'aset'],
        'UAT Internal' => ['UAT Internal', true, null],
    ];

    public function handle(BuatTokenKlienApi $buatToken): int
    {
        if ($this->laravel->isProduction()) {
            $this->error('regulasi:siapkan-uat tidak boleh dijalankan di produksi (APP_ENV=production).');

            return self::FAILURE;
        }

        if ($this->option('segar')) {
            $this->call('migrate:fresh', ['--force' => true]);
        }

        $this->call('db:seed', [
            '--class' => $this->option('tanpa-seed') ? PeranDanIzinSeeder::class : DatabaseSeeder::class,
            '--force' => true,
        ]);

        $sandi = (string) ($this->option('sandi') ?: config('regulasi.uat.password') ?: '');
        $sandiAcak = $sandi === '';

        if ($sandiAcak) {
            $sandi = Str::password(16, symbols: false);
        } elseif (mb_strlen($sandi) < 8) {
            $this->error('Kata sandi UAT minimal 8 karakter.');

            return self::FAILURE;
        }

        $baris = [];

        foreach (self::AKUN as $surel => [$nama, $peran, $kategori]) {
            $pengguna = User::query()->firstOrNew(['email' => $surel]);
            $pengguna->forceFill([
                'name' => $nama,
                'password' => $sandi,
                'aktif' => true,
                'email_verified_at' => $pengguna->email_verified_at ?? now(),
            ])->save();
            $pengguna->syncRoles([$peran->value]);
            $pengguna->kategoriSistem()->sync(KategoriSistem::query()->whereIn('kode', $kategori)->pluck('id')->all());

            $baris[] = [$peran->value, $surel, $kategori === [] ? '—' : implode(', ', $kategori), $pengguna->wajibMfa() ? 'wajib (diatur saat masuk pertama)' : '—'];
        }

        $this->table(['Peran', 'Akun', 'Kategori', 'MFA'], $baris);
        $this->line($sandiAcak
            ? "Kata sandi semua akun uji (acak, tampil sekali): <options=bold>{$sandi}</>"
            : 'Kata sandi semua akun uji: sesuai --sandi/UAT_PASSWORD.');

        $this->klienApi($buatToken);

        $this->info('Data UAT siap. Lihat docs/uat/hasil-uat.md untuk skenario dan lembar hasil.');

        return self::SUCCESS;
    }

    private function klienApi(BuatTokenKlienApi $buatToken): void
    {
        $pembuat = User::query()->where('email', 'uat.superadmin@unsil.ac.id')->firstOrFail();
        $baris = [];

        foreach (self::KLIEN as $namaKlien => [$namaToken, $internal, $kodeKategori]) {
            $klien = KlienApi::query()->firstOrCreate(['nama' => $namaKlien], [
                'kategori_sistem_id' => $kodeKategori !== null ? KategoriSistem::query()->where('kode', $kodeKategori)->value('id') : null,
                'kontak_nama' => 'Penguji UAT',
                'aktif' => true,
            ]);
            $klien->forceFill(['aktif' => true])->save();
            $klien->tokens()->where('name', $namaToken)->delete();

            $token = $buatToken->handle($klien, $namaToken, $internal, 90, $pembuat);
            $baris[] = [$namaKlien, $internal ? 'dokumen:baca, dokumen:baca-internal' : 'dokumen:baca', $token->plainTextToken];
        }

        $this->table(['Klien API', 'Abilities', 'Token (tampil sekali)'], $baris);
    }
}
