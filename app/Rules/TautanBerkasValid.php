<?php

namespace App\Rules;

use App\Enums\JenisTautan;
use App\Services\Berkas\PenguraiTautan;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validasi tautan berkas (02-ARSITEKTUR §4.2, BR-13): hanya https, tanpa userinfo, port 443, host bukan IP,
 * host cocok daftar putih config('berkas.domain_diizinkan') per label, bukan pemendek URL, bukan folder Drive,
 * panjang ≤ 2048. Naskah utama di Google Drive/Docs wajib menunjuk satu berkas (drive_file_id terekstrak).
 */
class TautanBerkasValid implements ValidationRule
{
    public function __construct(private readonly ?JenisTautan $jenis = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $pesan = self::periksa($value, $this->jenis);

        if ($pesan !== null) {
            $fail($pesan);
        }
    }

    /**
     * Pesan galat yang menjelaskan cara memperbaiki, atau null bila sah.
     */
    public static function periksa(mixed $url, ?JenisTautan $jenis = null): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return 'Tautan berkas wajib diisi.';
        }

        if (strlen($url) > (int) config('berkas.panjang_maks', 2048)) {
            return 'Tautan terlalu panjang (maks. 2048 karakter). Salin ulang tautan berbagi yang ringkas.';
        }

        $bagian = parse_url($url);

        if ($bagian === false || strtolower($bagian['scheme'] ?? '') !== 'https' || blank($bagian['host'] ?? null)) {
            return 'Tautan harus berupa alamat https:// yang lengkap. Salin ulang tautan berbagi dari Google Drive atau laman sumber resmi.';
        }

        if (isset($bagian['user']) || isset($bagian['pass'])) {
            return 'Tautan tidak boleh memuat nama pengguna/kata sandi (bagian sebelum "@"). Salin ulang tautan berbagi.';
        }

        if (isset($bagian['port']) && (int) $bagian['port'] !== 443) {
            return 'Tautan tidak boleh memakai port khusus. Gunakan alamat https biasa tanpa ":port".';
        }

        $host = strtolower(rtrim($bagian['host'], '.'));

        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false) {
            return 'Gunakan nama domain (mis. drive.google.com atau peraturan.go.id), bukan alamat IP.';
        }

        if (in_array($host, array_map('strtolower', (array) config('berkas.pemendek_ditolak', [])), true)) {
            return 'Pemendek URL (bit.ly, s.id, dan sejenisnya) tidak diterima. Tempel tautan asli Google Drive atau laman sumber resmi.';
        }

        if (! self::hostDiizinkan($host, (array) config('berkas.domain_diizinkan', []))) {
            return "Domain {$host} tidak termasuk daftar yang diizinkan. Gunakan Google Drive/Docs, *.unsil.ac.id, atau sumber resmi pemerintah (peraturan.go.id, jdih.*.go.id, peraturan.bpk.go.id).";
        }

        $pengurai = app(PenguraiTautan::class);

        if ($pengurai->adalahFolder($url)) {
            return 'Gunakan tautan berkas Google Drive, bukan tautan folder. Buka berkasnya, lalu Bagikan → Salin link.';
        }

        if ($jenis === JenisTautan::NaskahUtama && $pengurai->penyedia($url)->dapatDipratinjau() && $pengurai->driveFileId($url) === null) {
            return 'Naskah utama di Google Drive harus menunjuk satu berkas (bentuk https://drive.google.com/file/d/…/view).';
        }

        return null;
    }

    public static function sah(mixed $url, ?JenisTautan $jenis = null): bool
    {
        return self::periksa($url, $jenis) === null;
    }

    /**
     * Pencocokan per label: "*" mewakili tepat satu label; selain itu harus sama persis.
     *
     * @param  array<int, mixed>  $daftar
     */
    public static function hostDiizinkan(string $host, array $daftar): bool
    {
        $labelHost = explode('.', $host);

        foreach ($daftar as $pola) {
            $labelPola = explode('.', strtolower((string) $pola));

            if (count($labelPola) !== count($labelHost)) {
                continue;
            }

            $cocok = true;

            foreach ($labelPola as $i => $label) {
                if ($label !== '*' && $label !== $labelHost[$i]) {
                    $cocok = false;
                    break;
                }

                if ($label === '*' && $labelHost[$i] === '') {
                    $cocok = false;
                    break;
                }
            }

            if ($cocok) {
                return true;
            }
        }

        return false;
    }
}
