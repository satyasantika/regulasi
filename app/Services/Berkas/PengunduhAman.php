<?php

namespace App\Services\Berkas;

use App\Enums\StatusCekTautan;
use App\Exceptions\Berkas\AksesTerbatas;
use App\Exceptions\Berkas\BerkasTerlaluBesar;
use App\Exceptions\Berkas\BukanPdf;
use App\Exceptions\Berkas\PemeriksaanCukup;
use App\Exceptions\Berkas\PengunduhanDitolak;
use App\Exceptions\Berkas\TautanTidakDapatDiunduh;
use App\Rules\TautanBerkasValid;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
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
 * 4. Batas ukuran dari Content-Length (on_headers) dan penghitung saat streaming (progress) — unduhan dihentikan
 *    begitu melewati batas; isi dialirkan langsung ke berkas sementara (sink), tidak ditampung di memori.
 * 5. Timeout sambung 5 dtk, total 30 dtk; User-Agent RegulasiFKIPUnsil/1.0; tanpa cookie & header otorisasi.
 * 6. Respons text/html (halaman login/konfirmasi Drive) ditolak; isi wajib diawali magic bytes %PDF-.
 * 7. Berkas sementara storage/app/tmp/ekstraksi/{uuid}.pdf izin 0600; dihapus bila unduhan gagal.
 */
class PengunduhAman
{
    public const string USER_AGENT = 'RegulasiFKIPUnsil/1.0';

    private const int TIMEOUT_SAMBUNG = 5;

    /** Batas byte yang boleh diterima saat pemeriksaan keteraksesan (GET Range). */
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
        $path = $this->buatBerkasSementara();
        // Penolakan dari callback transfer disimpan di sini: Guzzle/Laravel dapat membungkus ulang exception-nya.
        /** @var PengunduhanDitolak|null $penolakan */
        $penolakan = null;

        try {
            ['respons' => $respons, 'url' => $urlAkhir] = $this->ikuti('GET', $url, [], $batasWaktu, [
                'sink' => $path,
                'on_headers' => function (ResponseInterface $r) use (&$penolakan): void {
                    if ($r->getStatusCode() >= 200 && $r->getStatusCode() < 300) {
                        try {
                            $this->periksaKepala($r->getHeaderLine('Content-Length'), $r->getHeaderLine('Content-Type'));
                        } catch (PengunduhanDitolak $e) {
                            throw $penolakan = $e;
                        }
                    }
                },
                'progress' => function (int $total, int $diunduh) use (&$penolakan): void {
                    if ($total > $this->batasByte() || $diunduh > $this->batasByte()) {
                        throw $penolakan = $this->terlaluBesar();
                    }
                },
            ]);

            $this->periksaRespons($respons);

            return $this->periksaIsi($path, $urlAkhir);
        } catch (Throwable $e) {
            @unlink($path);

            throw $penolakan ?? $e;
        }
    }

    /**
     * Keteraksesan tautan tanpa menyimpan isi: HEAD, lalu GET Range 0-1023 bila HEAD tidak berhasil (GET dihentikan
     * setelah header diterima atau isi melewati 64 KB). Pengalihan ke halaman masuk Google atau 401/403 berarti
     * berkas ada dengan akses terbatas (dapat diakses).
     */
    public function periksa(string $url): StatusCekTautan
    {
        $statusKepala = null;

        try {
            $batasWaktu = microtime(true) + (int) config('berkas.timeout_cek_detik', 10);
            $status = $this->ikuti('HEAD', $url, [], $batasWaktu)['respons']->status();

            if ($status < 200 || $status >= 300) {
                $status = $this->ikuti('GET', $url, ['Range' => 'bytes=0-1023'], $batasWaktu, [
                    'on_headers' => function (ResponseInterface $r) use (&$statusKepala): void {
                        if (! in_array($r->getStatusCode(), [301, 302, 303, 307, 308], true)) {
                            $statusKepala = $r->getStatusCode();

                            throw new PemeriksaanCukup;
                        }
                    },
                    'progress' => function (int $total, int $diunduh): void {
                        if ($diunduh > self::UKURAN_POTONGAN) {
                            throw new PemeriksaanCukup;
                        }
                    },
                ])['respons']->status();
            }
        } catch (AksesTerbatas) {
            return StatusCekTautan::DapatDiakses;
        } catch (Throwable) {
            $status = $statusKepala;
        }

        return $status !== null && (($status >= 200 && $status < 300) || in_array($status, [401, 403], true))
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
     * @param  array<string, mixed>  $opsi  Opsi Guzzle tambahan (sink, progress, on_headers).
     * @return array{respons: Response, url: string}
     */
    private function ikuti(string $metode, string $url, array $header, float $batasWaktu, array $opsi = []): array
    {
        $maks = (int) config('berkas.maks_redirect', 3);

        for ($lompatan = 0; ; $lompatan++) {
            $ip = $this->validasi($url);
            $host = strtolower(rtrim((string) parse_url($url, PHP_URL_HOST), '.'));

            try {
                $respons = $this->klien($host, $ip, $batasWaktu)->withOptions($opsi)->withHeaders($header)->send($metode, $url);
            } catch (Throwable $e) {
                throw self::galatAsal($e, $host);
            }

            if (! $respons->redirect()) {
                return ['respons' => $respons, 'url' => $url];
            }

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
     * Status, Content-Length, dan Content-Type respons akhir (langkah 4 & 6).
     */
    private function periksaRespons(Response $respons): void
    {
        if (in_array($respons->status(), [401, 403, 404, 410], true)) {
            throw new TautanTidakDapatDiunduh("Sumber menolak unduhan (HTTP {$respons->status()}).");
        }

        if (! $respons->successful()) {
            throw new RuntimeException("Sumber membalas HTTP {$respons->status()}.");
        }

        $this->periksaKepala($respons->header('Content-Length'), $respons->header('Content-Type'));
    }

    /**
     * @throws BerkasTerlaluBesar|TautanTidakDapatDiunduh
     */
    private function periksaKepala(string $panjang, string $tipe): void
    {
        if ($panjang !== '' && is_numeric($panjang) && (int) $panjang > $this->batasByte()) {
            throw $this->terlaluBesar();
        }

        $tipe = strtolower(trim(explode(';', $tipe)[0]));

        if ($tipe === 'text/html' || $tipe === 'application/xhtml+xml') {
            throw new TautanTidakDapatDiunduh('Sumber membalas halaman web (Drive meminta masuk/konfirmasi), bukan berkas.');
        }
    }

    /**
     * Berkas sementara kosong storage/app/tmp/ekstraksi/{uuid}.pdf dengan izin 0600 (langkah 7).
     */
    private function buatBerkasSementara(): string
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

        fclose($tulis);
        chmod($path, 0600);

        return $path;
    }

    /**
     * Ukuran akhir (jaring pengaman batas) dan magic bytes %PDF- (langkah 4 & 6), lalu hash isi.
     */
    private function periksaIsi(string $path, string $urlAkhir): BerkasSementara
    {
        clearstatcache(true, $path);
        $ukuran = (int) filesize($path);

        if ($ukuran > $this->batasByte()) {
            throw $this->terlaluBesar();
        }

        if ((string) file_get_contents($path, false, null, 0, 5) !== '%PDF-') {
            throw new BukanPdf('Isi tautan bukan berkas PDF.');
        }

        chmod($path, 0600);

        return new BerkasSementara($path, $ukuran, (string) hash_file('sha256', $path), $urlAkhir);
    }

    private function terlaluBesar(): BerkasTerlaluBesar
    {
        return new BerkasTerlaluBesar('Ukuran berkas melebihi '.config('berkas.ukuran_maks_unduhan_mb').' MB.');
    }

    /**
     * Galat transfer: penolakan terkendali (dari on_headers/progress) dikembalikan apa adanya; lainnya menjadi
     * RuntimeException (diulang oleh job).
     */
    private static function galatAsal(Throwable $galat, string $host): Throwable
    {
        for ($e = $galat; $e !== null; $e = $e->getPrevious()) {
            if ($e instanceof PengunduhanDitolak || $e instanceof PemeriksaanCukup) {
                return $e;
            }
        }

        return new RuntimeException("Permintaan ke {$host} gagal: ".$galat->getMessage(), previous: $galat);
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
