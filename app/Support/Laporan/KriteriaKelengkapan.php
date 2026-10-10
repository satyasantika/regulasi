<?php

namespace App\Support\Laporan;

use App\Enums\JenisTautan;
use App\Enums\StatusCekTautan;
use App\Enums\StatusEkstraksi;
use App\Models\Dokumen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Kriteria kelengkapan metadata dokumen (PRD §8 "Kelengkapan metadata", US-LAP-02) sebagai ekspresi SQL
 * boolean atas tabel `dokumen`. Ekspresi hanya memuat konstanta enum (bukan masukan pengguna), sehingga aman
 * dipakai di selectRaw/whereRaw dan dihitung di basis data (tanpa N+1).
 */
final class KriteriaKelengkapan
{
    /**
     * Kriteria yang membentuk skor kelengkapan (kunci → label kolom).
     *
     * @var array<string, string>
     */
    public const array LABEL = [
        'abstrak' => 'Abstrak',
        'naskah_utama' => 'Naskah utama',
        'teks' => 'Teks terekstraksi',
        'tanggal_penetapan' => 'Tanggal penetapan',
        'tanggal_berlaku' => 'Tanggal berlaku',
        'tag' => 'Tag',
        'sumber' => 'Sumber',
    ];

    /**
     * Ekspresi SQL boolean bahwa kriteria terpenuhi untuk baris `dokumen`.
     */
    public static function sql(string $kriteria): string
    {
        $tautan = "SELECT 1 FROM tautan_berkas tb WHERE tb.pemilik_type = 'dokumen' AND tb.pemilik_id = dokumen.id AND tb.deleted_at IS NULL";

        return match ($kriteria) {
            'abstrak' => "(dokumen.abstrak IS NOT NULL AND TRIM(dokumen.abstrak) <> '')",
            'naskah_utama' => "EXISTS ({$tautan} AND tb.jenis = '".JenisTautan::NaskahUtama->value."')",
            'teks' => "EXISTS (SELECT 1 FROM tautan_berkas tb JOIN ekstraksi_teks et ON et.tautan_berkas_id = tb.id WHERE tb.pemilik_type = 'dokumen' AND tb.pemilik_id = dokumen.id AND tb.deleted_at IS NULL AND et.status = '".StatusEkstraksi::Berhasil->value."')",
            'tanggal_penetapan' => '(dokumen.tanggal_penetapan IS NOT NULL)',
            'tanggal_berlaku' => '(dokumen.tanggal_berlaku IS NOT NULL)',
            'tag' => 'EXISTS (SELECT 1 FROM dokumen_tag dt WHERE dt.dokumen_id = dokumen.id)',
            'sumber' => "(dokumen.sumber IS NOT NULL AND TRIM(dokumen.sumber) <> '')",
            'tautan_bermasalah' => "EXISTS ({$tautan} AND tb.status_cek = '".StatusCekTautan::TidakDapatDiakses->value."')",
            default => throw new InvalidArgumentException("Kriteria kelengkapan [{$kriteria}] tidak dikenal."),
        };
    }

    /**
     * Skor kelengkapan 0–100 (bilangan bulat) = kriteria terpenuhi / jumlah kriteria.
     */
    public static function sqlSkor(): string
    {
        $jumlah = implode(' + ', array_map(fn (string $k): string => '(CASE WHEN '.self::sql($k).' THEN 1 ELSE 0 END)', array_keys(self::LABEL)));

        return 'ROUND(('.$jumlah.') * 100 / '.count(self::LABEL).')';
    }

    /**
     * Ekspresi bahwa SEMUA kriteria terpenuhi.
     */
    public static function sqlLengkap(): string
    {
        return '('.implode(' AND ', array_map(fn (string $k): string => self::sql($k), array_keys(self::LABEL))).')';
    }

    /**
     * Tambahkan kolom boolean `lengkap_{kriteria}` dan `skor_kelengkapan` ke query.
     *
     * @param  Builder<Dokumen>  $query
     * @return Builder<Dokumen>
     */
    public static function pilihKolom(Builder $query): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select('dokumen.*');
        }

        foreach (array_keys(self::LABEL) as $kriteria) {
            $query->selectRaw('(CASE WHEN '.self::sql($kriteria).' THEN 1 ELSE 0 END) AS lengkap_'.$kriteria);
        }

        return $query->selectRaw(self::sqlSkor().' AS skor_kelengkapan');
    }

    /**
     * Rata-rata skor kelengkapan dan jumlah dokumen lengkap per kategori sistem (dokumen terbit, lingkup pengguna).
     *
     * @return list<array{kode: string, nama: string, jumlah: int, lengkap: int, skor: int}>
     */
    public static function ringkasanPerKategori(LingkupLaporan $lingkup): array
    {
        $terbit = $lingkup->terapkan(Dokumen::query()->terbit())->toBase()
            ->select('dokumen.id')
            ->selectRaw(self::sqlSkor().' AS skor')
            ->selectRaw('(CASE WHEN '.self::sqlLengkap().' THEN 1 ELSE 0 END) AS lengkap');

        return DB::table('kategori_sistem')
            ->whereNull('kategori_sistem.deleted_at')
            ->where('kategori_sistem.aktif', true)
            ->when($lingkup->kategoriIds !== null, fn ($q) => $q->whereIn('kategori_sistem.id', $lingkup->kategoriIds === [] ? ['00000000-0000-0000-0000-000000000000'] : (array) $lingkup->kategoriIds))
            ->leftJoin('dokumen_kategori_sistem', 'dokumen_kategori_sistem.kategori_sistem_id', '=', 'kategori_sistem.id')
            ->leftJoinSub($terbit, 'terbit', 'terbit.id', '=', 'dokumen_kategori_sistem.dokumen_id')
            ->groupBy('kategori_sistem.id', 'kategori_sistem.kode', 'kategori_sistem.nama', 'kategori_sistem.urutan')
            ->orderBy('kategori_sistem.urutan')
            ->orderBy('kategori_sistem.nama')
            ->selectRaw('kategori_sistem.kode AS kode, kategori_sistem.nama AS nama, COUNT(terbit.id) AS jumlah, COALESCE(SUM(terbit.lengkap), 0) AS lengkap, COALESCE(AVG(terbit.skor), 0) AS skor')
            ->get()
            ->map(fn (object $b): array => [
                'kode' => (string) $b->kode,
                'nama' => (string) $b->nama,
                'jumlah' => (int) $b->jumlah,
                'lengkap' => (int) $b->lengkap,
                'skor' => (int) round((float) $b->skor),
            ])
            ->values()
            ->all();
    }
}
