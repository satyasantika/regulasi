<?php

namespace App\Services\Berkas;

use App\Enums\StatusCekTautan;
use App\Exceptions\Berkas\AksesTerbatas;
use App\Exceptions\Berkas\BerkasTerlaluBesar;
use App\Exceptions\Berkas\BukanPdf;
use App\Exceptions\Berkas\TautanTidakDapatDiunduh;
use App\Rules\TautanBerkasValid;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Pengambil HTTP anti-SSRF untuk pemeriksaan tautan dan ekstraksi teks (02-ARSITEKTUR §4.3). Satu-satunya jalan
 * aplikasi mengunduh URL milik pengguna:
 *
 * 1. URL: https, tanpa userinfo, port 443, host bukan IP, host di domain_diizinkan + domain_unduhan_tambahan.
 * 2. Resolusi DNS sendiri; tolak bila SALAH SATU IP privat/khusus (termasuk IPv4-mapped). Sambungan dipaku ke IP
 *    yang sudah divalidasi lewat CURLOPT_RESOLVE (cegah DNS rebinding).
 * 3. Tanpa redirect otomatis; maksimal `maks_redirect` pengalihan, setiap Location divalidasi ulang langkah 1–2.
 * 4. Batas ukuran dari Content-Length dan penghitung saat streaming.
 * 5. Timeout sambung 5 dtk, total 30 dtk; User-Agent RegulasiFKIPUnsil/1.0; tanpa cookie & header otorisasi.
 * 6. Respons text/html (halaman login/konfirmasi Drive) ditolak; isi wajib diawali magic bytes %PDF-.
 * 7. Berkas sementara storage/app/tmp/ekstraksi/{uuid}.pdf izin 0600; dihapus bila unduhan gagal.
 */
class PengunduhAman
{
    public const string USER_AGENT = 'RegulasiFKIPUnsil/1.0';

    private const int TIMEOUT_SAMBUNG = 5;

    private const int UKURAN_POTONGAN = 65536;

    /** Rentang IPv4 privat/khusus (CIDR) yang tidak boleh dihubungi. */
    private const array IPV4_TERLARANG = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
        '192.168.0.0/16', '192.0.0.0/24', '198.18.0.0/15', '224.0.0.0/4', '240.0.0.0/4',
    ];

    /** Rentang IPv6 terlarang; ::/96 (IPv4-compatible, termasuk :: dan ::1) dan ::ffff:0:0/96 (IPv4-mapped). */
    private const array IPV6_TERLARANG = [
        '::/96', '::ffff:0:0/96', 'fc00::/7', 'fe80::/10', 'ff00::/8', '64:ff9b::/96', '2002::/16',
    ];

    /** Host halaman masuk Google: tautan ada tetapi aksesnya terbatas. */
    private const array HOST_LOGIN = ['accounts.google.com'];

    public function __construct(private readonly PemecahDns $dns) {}

    /**
     * Unduh PDF ke berkas sementara. Pemanggil wajib memanggil BerkasSementara::hapus() di finally.
     *
     * @throws TautanTidakDapatDiunduh|BerkasTerlaluBesar|BukanPdf
     */
    public function unduh(string $url): BerkasSementara
    {
        $batasWaktu = microtime(true) + (int) config('berkas.timeout_unduh_detik', 30);
        ['respons' => $respons, 'url' => $urlAkhir] = $this->ikuti('GET', $url, [], $batasWaktu);

        $this->periksaRespons($respons);

        return $this->simpanSementara($respons, $urlAkhir);
    }

    /**
     * Keteraksesan tautan tanpa menyimpan isi: HEAD, lalu GET Range 0-1023 bila HEAD tidak berhasil.
     * Pengalihan ke halaman masuk Google atau 401/403 berarti berkas ada dengan akses terbatas (dapat diakses).
     */
    public function periksa(string $url): StatusCekTautan
    {
        try {
            $batasWaktu = microtime(true) + (int) config('berkas.timeout_cek_detik', 10);
            $respons = $this->ikuti('HEAD', $url, [], $batasWaktu)['respons'];

            if (! $respons->successful()) {
                $respons = $this->ikuti('GET', $url, ['Range' => 'bytes=0-1023'], $batasWaktu)['respons'];
                $respons->toPsrResponse()->getBody()->close();
            }
        } catch (AksesTerbatas) {
            return StatusCekTautan::DapatDiakses;
        } catch (Throwable) {
            return StatusCekTautan::TidakDapatDiakses;
        }

        return $respons->successful() || in_array($respons->status(), [401, 403], true)
            ? StatusCekTautan::DapatDiakses
            : StatusCekTautan::TidakDapatDiakses;
    }

    /**
     * Validasi URL & host lalu kembalikan IP publik pertama untuk dipaku (langkah 1–2).
     *
     * @throws TautanTidakDapatDiunduh
     */
    public function validasi(string $url): string
    {
        $bagian = parse_url($url);

        if ($bagian === false || strtolower($bagian['scheme'] ?? '') !== 'https' || blank($bagian['host'] ?? null)) {
            throw new TautanTidakDapatDiunduh('Hanya alamat https:// yang boleh diunduh.');
        }

        if (isset($bagian['user']) || isset($bagian['pass'])) {
            throw new TautanTidakDapatDiunduh('Alamat unduhan tidak boleh memuat userinfo.');
        }

        if (isset($bagian['port']) && (int) $bagian['port'] !== 443) {
            throw new TautanTidakDapatDiunduh('Alamat unduhan tidak boleh memakai port selain 443.');
        }

        $host = strtolower(rtrim($bagian['host'], '.'));

        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false) {
            throw new TautanTidakDapatDiunduh('Alamat unduhan tidak boleh berupa alamat IP.');
        }

        $daftar = array_merge((array) config('berkas.domain_diizinkan', []), (array) config('berkas.domain_unduhan_tambahan', []));

        if (! TautanBerkasValid::hostDiizinkan($host, $daftar)) {
            throw new TautanTidakDapatDiunduh("Host {$host} tidak termasuk daftar putih unduhan.");
        }

        $alamat = $this->dns->alamat($host);

        if ($alamat === []) {
            throw new TautanTidakDapatDiunduh("Host {$host} tidak dapat diresolusi.");
        }

        foreach ($alamat as $ip) {
            if (self::ipTerlarang($ip)) {
                throw new TautanTidakDapatDiunduh("Host {$host} mengarah ke alamat IP privat/khusus.");
            }
        }

        return $alamat[0];
    }

    /**
     * Opsi Guzzle untuk satu permintaan: tanpa redirect otomatis, tanpa cookie, sambungan dipaku ke IP tervalidasi.
     *
     * @return array<string, mixed>
     */
    public function opsiSambungan(string $host, string $ip): array
    {
        $alamat = str_contains($ip, ':') ? "[{$ip}]" : $ip;

        return [
            'allow_redirects' => false,
            'cookies' => false,
            'stream' => true,
            'http_errors' => false,
            'curl' => [CURLOPT_RESOLVE => ["{$host}:443:{$alamat}"]],
        ];
    }

    /**
     * IP privat/loopback/link-local/khusus/IPv4-mapped (atau tidak sah) → true.
     */
    public static function ipTerlarang(string $ip): bool
    {
        $biner = @inet_pton($ip);

        if ($biner === false) {
            return true;
        }

        $rentang = strlen($biner) === 4 ? self::IPV4_TERLARANG : self::IPV6_TERLARANG;

        foreach ($rentang as $cidr) {
            if (self::dalamCidr($biner, $cidr)) {
                return true;
            }
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    private static function dalamCidr(string $biner, string $cidr): bool
    {
        [$jaringan, $panjang] = explode('/', $cidr);
        $binerJaringan = (string) inet_pton($jaringan);

        if (strlen($binerJaringan) !== strlen($biner)) {
            return false;
        }

        $panjang = (int) $panjang;
        $byteUtuh = intdiv($panjang, 8);
        $sisaBit = $panjang % 8;

        if (substr($biner, 0, $byteUtuh) !== substr($binerJaringan, 0, $byteUtuh)) {
            return false;
        }

        if ($sisaBit === 0) {
            return true;
        }

        $topeng = (0xFF << (8 - $sisaBit)) & 0xFF;

        return (ord($biner[$byteUtuh]) & $topeng) === (ord($binerJaringan[$byteUtuh]) & $topeng);
    }

    /**
     * Kirim permintaan dan ikuti pengalihan secara manual (langkah 3).
     *
     * @param  array<string, string>  $header
     * @return array{respons: Response, url: string}
     */
    private function ikuti(string $metode, string $url, array $header, float $batasWaktu): array
    {
        $maks = (int) config('berkas.maks_redirect', 3);

        for ($lompatan = 0; ; $lompatan++) {
            $ip = $this->validasi($url);
            $host = strtolower(rtrim((string) parse_url($url, PHP_URL_HOST), '.'));

            try {
                $respons = $this->klien($host, $ip, $batasWaktu)->withHeaders($header)->send($metode, $url);
            } catch (ConnectionException $e) {
                throw new RuntimeException("Sambungan ke {$host} gagal: ".$e->getMessage(), previous: $e);
            }

            if (! $respons->redirect()) {
                return ['respons' => $respons, 'url' => $url];
            }

            $respons->toPsrResponse()->getBody()->close();

            if ($lompatan >= $maks) {
                throw new TautanTidakDapatDiunduh("Pengalihan melebihi {$maks} kali.");
            }

            $lokasi = trim((string) $respons->header('Location'));

            if ($lokasi === '') {
                throw new TautanTidakDapatDiunduh('Pengalihan tanpa alamat tujuan.');
            }

            $url = self::absolut($lokasi, $url);

            if (in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), self::HOST_LOGIN, true)) {
                throw new AksesTerbatas('Berkas meminta masuk akun Google (dibagikan terbatas).');
            }

            if ($respons->status() === 303) {
                $metode = $metode === 'HEAD' ? 'HEAD' : 'GET';
            }
        }
    }

    private function klien(string $host, string $ip, float $batasWaktu): PendingRequest
    {
        $sisa = (int) ceil($batasWaktu - microtime(true));

        if ($sisa <= 0) {
            throw new RuntimeException('Batas waktu unduhan terlampaui.');
        }

        return Http::withOptions($this->opsiSambungan($host, $ip))
            ->connectTimeout(min(self::TIMEOUT_SAMBUNG, $sisa))
            ->timeout($sisa)
            ->withUserAgent(self::USER_AGENT)
            ->withHeaders(['Accept' => 'application/pdf,*/*;q=0.5']);
    }

    /**
     * Status, Content-Length, dan Content-Type (langkah 4 & 6) sebelum isi dibaca.
     */
    private function periksaRespons(Response $respons): void
    {
        if (in_array($respons->status(), [401, 403, 404, 410], true)) {
            $respons->toPsrResponse()->getBody()->close();

            throw new TautanTidakDapatDiunduh("Sumber menolak unduhan (HTTP {$respons->status()}).");
        }

        if (! $respons->successful()) {
            $respons->toPsrResponse()->getBody()->close();

            throw new RuntimeException("Sumber membalas HTTP {$respons->status()}.");
        }

        $panjang = $respons->header('Content-Length');

        if ($panjang !== '' && is_numeric($panjang) && (int) $panjang > $this->batasByte()) {
            $respons->toPsrResponse()->getBody()->close();

            throw new BerkasTerlaluBesar('Ukuran berkas melebihi '.config('berkas.ukuran_maks_unduhan_mb').' MB.');
        }

        $tipe = strtolower(trim(explode(';', $respons->header('Content-Type'))[0]));

        if ($tipe === 'text/html' || $tipe === 'application/xhtml+xml') {
            $respons->toPsrResponse()->getBody()->close();

            throw new TautanTidakDapatDiunduh('Sumber membalas halaman web (Drive meminta masuk/konfirmasi), bukan berkas.');
        }
    }

    /**
     * Alirkan isi ke berkas sementara 0600 dengan penghitung ukuran dan pemeriksaan magic bytes (langkah 4, 6, 7).
     */
    private function simpanSementara(Response $respons, string $urlAkhir): BerkasSementara
    {
        $folder = storage_path('app/'.trim((string) config('berkas.folder_sementara', 'tmp/ekstraksi'), '/'));

        if (! is_dir($folder) && ! @mkdir($folder, 0700, true) && ! is_dir($folder)) {
            throw new RuntimeException('Folder sementara tidak dapat dibuat.');
        }

        $path = $folder.'/'.Str::uuid7().'.pdf';
        $tulis = @fopen($path, 'xb');

        if ($tulis === false) {
            throw new RuntimeException('Berkas sementara tidak dapat dibuat.');
        }

        chmod($path, 0600);

        $badan = $respons->toPsrResponse()->getBody();
        $hash = hash_init('sha256');
        $ukuran = 0;
        $awal = '';

        try {
            while (! $badan->eof()) {
                $potongan = $badan->read(self::UKURAN_POTONGAN);

                if ($potongan === '') {
                    continue;
                }

                $ukuran += strlen($potongan);

                if ($ukuran > $this->batasByte()) {
                    throw new BerkasTerlaluBesar('Ukuran berkas melebihi '.config('berkas.ukuran_maks_unduhan_mb').' MB.');
                }

                if (strlen($awal) < 5) {
                    $awal .= substr($potongan, 0, 5 - strlen($awal));

                    if (strlen($awal) >= 5 && $awal !== '%PDF-') {
                        throw new BukanPdf('Isi tautan bukan berkas PDF.');
                    }
                }

                hash_update($hash, $potongan);
                fwrite($tulis, $potongan);
            }

            if ($awal !== '%PDF-') {
                throw new BukanPdf('Isi tautan bukan berkas PDF.');
            }
        } catch (Throwable $e) {
            fclose($tulis);
            @unlink($path);

            throw $e;
        } finally {
            $badan->close();
        }

        fclose($tulis);

        return new BerkasSementara($path, $ukuran, hash_final($hash), $urlAkhir);
    }

    private function batasByte(): int
    {
        return (int) config('berkas.ukuran_maks_unduhan_mb', 30) * 1024 * 1024;
    }

    private static function absolut(string $lokasi, string $dasar): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $lokasi) === 1) {
            return $lokasi;
        }

        $bagian = parse_url($dasar);
        $asal = ($bagian['scheme'] ?? 'https').'://'.($bagian['host'] ?? '');

        if (str_starts_with($lokasi, '//')) {
            return ($bagian['scheme'] ?? 'https').':'.$lokasi;
        }

        if (str_starts_with($lokasi, '/')) {
            return $asal.$lokasi;
        }

        $jalur = $bagian['path'] ?? '/';

        return $asal.substr($jalur, 0, (int) strrpos($jalur, '/') + 1).$lokasi;
    }
}
