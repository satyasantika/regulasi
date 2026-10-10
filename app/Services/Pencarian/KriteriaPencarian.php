<?php

namespace App\Services\Pencarian;

/**
 * Kriteria pencarian dokumen untuk portal & API (02-ARSITEKTUR §5.2, US-CARI-01..03). Nilai faset memakai kode:
 * jenis = jenis_dokumen.kode, kategori = kategori_sistem.kode, badan = badan_penetap.slug,
 * status = status_berlaku, kelompok = KelompokJenisDokumen.
 */
final readonly class KriteriaPencarian
{
    public const array URUTAN = ['relevan', 'terbaru', 'tahun_desc', 'tahun_asc', 'penetapan_asc', 'dibuka'];

    public const int PER_HALAMAN_MAKS = 50;

    /** Batas page[size] API v1 (F9.2). */
    public const int PER_HALAMAN_MAKS_API = 100;

    public string $q;

    public string $urut;

    public int $halaman;

    public int $perHalaman;

    /**
     * @param  list<string>  $jenis
     * @param  list<int>  $tahun
     * @param  list<string>  $status
     * @param  list<string>  $kategori
     * @param  list<string>  $badan
     * @param  list<string>  $kelompok
     * @param  int  $perHalamanMaks  batas per halaman (portal 50, API 100)
     */
    public function __construct(
        ?string $q = null,
        public array $jenis = [],
        public array $tahun = [],
        public array $status = [],
        public array $kategori = [],
        public array $badan = [],
        public array $kelompok = [],
        string $urut = 'relevan',
        int $halaman = 1,
        int $perHalaman = 20,
        public bool $sertakanInternal = false,
        int $perHalamanMaks = self::PER_HALAMAN_MAKS,
    ) {
        $this->q = trim(mb_substr((string) $q, 0, 200));
        $this->urut = in_array($urut, self::URUTAN, true) ? $urut : 'relevan';
        $this->halaman = max(1, $halaman);
        $this->perHalaman = max(1, min($perHalamanMaks, $perHalaman));
    }

    /**
     * Dari parameter permintaan (array atau string dipisah koma).
     *
     * @param  array<string, mixed>  $data
     */
    public static function dari(array $data, bool $sertakanInternal = false, int $perHalamanMaks = self::PER_HALAMAN_MAKS): self
    {
        return new self(
            q: is_scalar($data['q'] ?? null) ? (string) $data['q'] : null,
            jenis: self::daftar($data['jenis'] ?? []),
            tahun: array_values(array_filter(array_map('intval', self::daftar($data['tahun'] ?? [])), fn (int $t): bool => $t > 0)),
            status: self::daftar($data['status'] ?? []),
            kategori: self::daftar($data['kategori'] ?? []),
            badan: self::daftar($data['badan'] ?? []),
            kelompok: self::daftar($data['kelompok'] ?? []),
            urut: is_string($data['urut'] ?? null) ? $data['urut'] : 'relevan',
            halaman: (int) ($data['halaman'] ?? 1),
            perHalaman: (int) ($data['per_halaman'] ?? 20),
            sertakanInternal: $sertakanInternal,
            perHalamanMaks: $perHalamanMaks,
        );
    }

    public function adaKataKunci(): bool
    {
        return $this->q !== '';
    }

    /**
     * Filter aktif per dimensi faset (kunci sama dengan HasilPencarian::$faset).
     *
     * @return array<string, list<string|int>>
     */
    public function filterFaset(): array
    {
        return [
            'jenis' => $this->jenis,
            'tahun' => $this->tahun,
            'status_berlaku' => $this->status,
            'kategori' => $this->kategori,
            'badan_penetap' => $this->badan,
        ];
    }

    /**
     * @return list<string>
     */
    private static function daftar(mixed $nilai): array
    {
        $butir = is_array($nilai) ? $nilai : explode(',', (string) (is_scalar($nilai) ? $nilai : ''));

        return array_values(array_unique(array_filter(
            array_map(fn (mixed $b): string => trim(is_scalar($b) ? (string) $b : ''), $butir),
            fn (string $b): bool => $b !== '' && mb_strlen($b) <= 100,
        )));
    }
}
