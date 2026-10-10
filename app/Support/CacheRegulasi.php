<?php

namespace App\Support;

use App\Enums\StatusBerlaku;
use App\Enums\Visibilitas;
use App\Models\BadanPenetap;
use App\Models\Dokumen;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

/**
 * Pembungkus cache Redis aplikasi (02-ARSITEKTUR §3.1). Kunci ditulis tanpa prefix `regulasi:`. Nilai ter-cache
 * selalu larik/skalar (bukan objek) karena cache.serializable_classes = false.
 */
class CacheRegulasi
{
    public const int TTL_MASTER_DETIK = 60 * 60 * 24;

    public const int TTL_SEHAT_DETIK = 60;

    public const int TTL_PUBLIK_DETIK = 60 * 60;

    public const int JUMLAH_TERBARU = 10;

    /**
     * Kunci cache publik (beranda & faset, 02-ARSITEKTUR §3.1) yang dibersihkan saat dokumen terbit/diarsipkan
     * atau status berlaku berubah (BR-20).
     *
     * @var list<string>
     */
    public const array KUNCI_PUBLIK = [
        'beranda:statistik',
        'beranda:terbaru',
        'faset:tahun',
        'sitemap',
    ];

    /**
     * Nama master yang dapat di-cache beserta modelnya.
     *
     * @var array<string, class-string<JenisDokumen|KategoriSistem|BadanPenetap>>
     */
    public const array MASTER = [
        'jenis-dokumen' => JenisDokumen::class,
        'kategori-sistem' => KategoriSistem::class,
        'badan-penetap' => BadanPenetap::class,
    ];

    /**
     * Daftar master aktif dan terurut (urutan lalu nama), di-cache 24 jam.
     *
     * @return Collection<int, JenisDokumen>|Collection<int, KategoriSistem>|Collection<int, BadanPenetap>
     */
    public static function master(string $nama): Collection
    {
        $model = self::modelMaster($nama);

        // Disimpan sebagai larik atribut mentah lalu di-hydrate: store Redis menolak unserialize objek
        // (config cache.serializable_classes = false).
        $baris = Cache::remember(self::kunciMaster($nama), self::TTL_MASTER_DETIK, fn (): array => $model::query()
            ->aktif()
            ->terurut()
            ->get()
            ->map(fn (Model $m): array => $m->getAttributes())
            ->all());

        return $model::hydrate($baris);
    }

    /**
     * Statistik beranda (US-PUB-01, BR-20): hanya dokumen terbit & publik (BR-01, BR-02), di-cache 60 menit.
     *
     * @return array{
     *     total: int,
     *     total_dibuka: int,
     *     per_jenis: list<array{kode: string, nama: string, slug: string, jumlah: int}>,
     *     per_status: array<string, int>,
     *     per_kategori: list<array{kode: string, nama: string, slug: string, ikon: string|null, jumlah: int}>
     * }
     */
    public static function statistikBeranda(): array
    {
        return Cache::remember('beranda:statistik', self::TTL_PUBLIK_DETIK, function (): array {
            $dasar = fn () => Dokumen::query()->terbit()->publik();

            $perJenis = $dasar()->toBase()
                ->join('jenis_dokumen', 'jenis_dokumen.id', '=', 'dokumen.jenis_dokumen_id')
                ->groupBy('jenis_dokumen.id', 'jenis_dokumen.kode', 'jenis_dokumen.nama', 'jenis_dokumen.slug', 'jenis_dokumen.urutan')
                ->orderBy('jenis_dokumen.urutan')
                ->orderBy('jenis_dokumen.nama')
                ->selectRaw('jenis_dokumen.kode, jenis_dokumen.nama, jenis_dokumen.slug, COUNT(*) AS jumlah')
                ->get()
                ->map(fn (object $b): array => ['kode' => (string) $b->kode, 'nama' => (string) $b->nama, 'slug' => (string) $b->slug, 'jumlah' => (int) $b->jumlah])
                ->filter(fn (array $b): bool => $b['jumlah'] > 0)
                ->values()
                ->all();

            $perStatus = $dasar()->toBase()
                ->groupBy('status_berlaku')
                ->selectRaw('status_berlaku, COUNT(*) AS jumlah')
                ->pluck('jumlah', 'status_berlaku')
                ->map(fn (mixed $jumlah): int => (int) $jumlah);

            $perKategori = DB::table('kategori_sistem')
                ->whereNull('kategori_sistem.deleted_at')
                ->where('kategori_sistem.aktif', true)
                ->leftJoin('dokumen_kategori_sistem', 'dokumen_kategori_sistem.kategori_sistem_id', '=', 'kategori_sistem.id')
                ->leftJoinSub($dasar()->toBase()->select('dokumen.id'), 'terbit', 'terbit.id', '=', 'dokumen_kategori_sistem.dokumen_id')
                ->groupBy('kategori_sistem.id', 'kategori_sistem.kode', 'kategori_sistem.nama', 'kategori_sistem.slug', 'kategori_sistem.ikon', 'kategori_sistem.urutan')
                ->orderBy('kategori_sistem.urutan')
                ->orderBy('kategori_sistem.nama')
                ->selectRaw('kategori_sistem.kode, kategori_sistem.nama, kategori_sistem.slug, kategori_sistem.ikon, COUNT(DISTINCT terbit.id) AS jumlah')
                ->get()
                ->map(fn (object $b): array => [
                    'kode' => (string) $b->kode,
                    'nama' => (string) $b->nama,
                    'slug' => (string) $b->slug,
                    'ikon' => $b->ikon !== null ? (string) $b->ikon : null,
                    'jumlah' => (int) $b->jumlah,
                ])
                ->values()
                ->all();

            return [
                'total' => $dasar()->count(),
                'total_dibuka' => (int) $dasar()->sum('jumlah_dibuka'),
                'per_jenis' => $perJenis,
                'per_status' => collect(StatusBerlaku::cases())
                    ->mapWithKeys(fn (StatusBerlaku $s): array => [$s->value => (int) ($perStatus[$s->value] ?? 0)])
                    ->all(),
                'per_kategori' => $perKategori,
            ];
        });
    }

    /**
     * Sepuluh dokumen terbit & publik terbaru (menurut tanggal publikasi), di-cache 60 menit sebagai larik
     * sederhana agar aman diserialisasi.
     *
     * @return list<array{id: string, slug: string, sebutan_singkat: string, judul: string, jenis: string, status_berlaku: string, dipublikasikan_pada: string|null, internal: bool}>
     */
    public static function terbaru(): array
    {
        return Cache::remember('beranda:terbaru', self::TTL_PUBLIK_DETIK, fn (): array => self::petakanTerbaru(
            Dokumen::query()->terbit()->publik(),
        ));
    }

    /**
     * Dokumen terbaru termasuk internal untuk pengguna login (BR-02); sengaja tidak di-cache bersama data publik.
     *
     * @return list<array{id: string, slug: string, sebutan_singkat: string, judul: string, jenis: string, status_berlaku: string, dipublikasikan_pada: string|null, internal: bool}>
     */
    public static function terbaruTermasukInternal(): array
    {
        return self::petakanTerbaru(Dokumen::query()->terbit());
    }

    /**
     * Tahun yang memiliki dokumen terbit & publik, menurun.
     *
     * @return list<int>
     */
    public static function tahunTersedia(): array
    {
        return Cache::remember('faset:tahun', self::TTL_PUBLIK_DETIK, fn (): array => Dokumen::query()
            ->terbit()
            ->publik()
            ->distinct()
            ->orderByDesc('tahun')
            ->pluck('tahun')
            ->map(fn (mixed $tahun): int => (int) $tahun)
            ->values()
            ->all());
    }

    public static function lupakanMaster(string $nama): void
    {
        self::modelMaster($nama);

        Cache::forget(self::kunciMaster($nama));
    }

    /**
     * Bersihkan cache publik (BR-20).
     */
    public static function lupakanPublik(): void
    {
        foreach (self::KUNCI_PUBLIK as $kunci) {
            Cache::forget($kunci);
        }
    }

    /**
     * Kesehatan Meilisearch (GET /health → status available), di-cache 60 detik (`pencarian:sehat`).
     */
    public static function pencarianSehat(): bool
    {
        return (bool) Cache::remember('pencarian:sehat', self::TTL_SEHAT_DETIK, function (): bool {
            $host = rtrim((string) config('scout.meilisearch.host'), '/');

            if ($host === '') {
                return false;
            }

            try {
                $respons = Http::connectTimeout(2)->timeout(2)->acceptJson()->get($host.'/health');

                return $respons->successful() && $respons->json('status') === 'available';
            } catch (Throwable) {
                return false;
            }
        });
    }

    /**
     * @param  Builder<Dokumen>  $query
     * @return list<array{id: string, slug: string, sebutan_singkat: string, judul: string, jenis: string, status_berlaku: string, dipublikasikan_pada: string|null, internal: bool}>
     */
    private static function petakanTerbaru(Builder $query): array
    {
        return $query
            ->with('jenisDokumen')
            ->orderByDesc('dipublikasikan_pada')
            ->orderByDesc('id')
            ->limit(self::JUMLAH_TERBARU)
            ->get()
            ->map(fn (Dokumen $d): array => [
                'id' => $d->id,
                'slug' => $d->slug,
                'sebutan_singkat' => $d->sebutan_singkat,
                'judul' => $d->judul,
                'jenis' => $d->jenisDokumen->nama,
                'status_berlaku' => $d->status_berlaku->value,
                'dipublikasikan_pada' => $d->dipublikasikan_pada?->toIso8601String(),
                'internal' => $d->visibilitas === Visibilitas::Internal,
            ])
            ->values()
            ->all();
    }

    /**
     * Nama master untuk sebuah model (dipakai observer), atau null bila bukan master ber-cache.
     */
    public static function namaMasterUntuk(Model $model): ?string
    {
        $nama = array_search($model::class, self::MASTER, true);

        return $nama === false ? null : $nama;
    }

    public static function kunciMaster(string $nama): string
    {
        return "master:{$nama}";
    }

    /**
     * @return class-string<JenisDokumen|KategoriSistem|BadanPenetap>
     */
    private static function modelMaster(string $nama): string
    {
        return self::MASTER[$nama] ?? throw new InvalidArgumentException("Master [{$nama}] tidak dikenal.");
    }
}
