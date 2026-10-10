<?php

namespace App\Support;

use App\Enums\JenisRelasi;
use App\Enums\JenisRiwayatStatus;
use App\Enums\JenisTautan;
use App\Enums\StatusCekTautan;
use App\Enums\StatusEkstraksi;
use App\Enums\StatusPublikasi;
use App\Models\Dokumen;
use App\Models\DokumenRelasi;
use App\Models\EkstraksiTeks;
use App\Models\RiwayatStatusDokumen;
use App\Models\TautanBerkas;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Angka ringkasan mingguan untuk admin-regulasi (US-NOT-03, 02-ARSITEKTUR §9): dokumen baru terbit 7 hari
 * terakhir, antrean verifikasi, kelengkapan dokumen terbit, tautan/ekstraksi bermasalah, isi berkas berubah,
 * dan relasi tertunda yang akan efektif 30 hari ke depan. Dokumen terhapus lunak tidak dihitung.
 */
final class RingkasanMingguan
{
    public const int BATAS_DAFTAR = 10;

    /**
     * @return array{
     *     sejak: string,
     *     terbit_baru: array{jumlah: int, daftar: list<string>},
     *     antrean: array{jumlah: int, tertua: string|null},
     *     kelengkapan: array{tanpa_abstrak: int, tanpa_naskah_utama: int, tanpa_teks: int},
     *     tautan_tidak_dapat_diakses: int,
     *     ekstraksi: array{gagal: int, tidak_dapat_diunduh: int},
     *     isi_berubah: int,
     *     relasi_tertunda: array{jumlah: int, daftar: list<string>}
     * }
     */
    public static function hitung(?CarbonImmutable $sekarang = null): array
    {
        $sekarang ??= CarbonImmutable::now();
        $sejak = $sekarang->subDays(7);

        $terbitBaru = Dokumen::query()
            ->where('status_publikasi', StatusPublikasi::Terbit->value)
            ->where('dipublikasikan_pada', '>=', $sejak)
            ->orderByDesc('dipublikasikan_pada');

        $relasiTertunda = DokumenRelasi::query()
            ->with(['dokumen', 'dokumenTerkait'])
            ->whereIn('jenis_relasi', array_map(fn (JenisRelasi $j): string => $j->value, JenisRelasi::arahAktif()))
            ->where('efektif', false)
            ->whereDate('efektif_pada', '>', $sekarang->toDateString())
            ->whereDate('efektif_pada', '<=', $sekarang->addDays(30)->toDateString())
            ->whereHas('dokumen', fn (Builder $q) => $q->where('status_publikasi', StatusPublikasi::Terbit->value))
            ->whereHas('dokumenTerkait')
            ->orderBy('efektif_pada');

        return [
            'sejak' => $sejak->toDateString(),
            'terbit_baru' => [
                'jumlah' => (clone $terbitBaru)->count(),
                'daftar' => $terbitBaru->limit(self::BATAS_DAFTAR)->pluck('sebutan_singkat')->map(fn (mixed $s): string => (string) $s)->values()->all(),
            ],
            'antrean' => self::antrean(),
            'kelengkapan' => [
                'tanpa_abstrak' => self::terbit()->where(fn (Builder $q) => $q->whereNull('abstrak')->orWhere('abstrak', ''))->count(),
                'tanpa_naskah_utama' => self::terbit()->where('tanpa_berkas', false)->whereDoesntHave('naskahUtama')->count(),
                'tanpa_teks' => self::terbit()->whereHas('tautanBerkas', fn (Builder $t) => $t
                    ->where('jenis', JenisTautan::NaskahUtama->value)
                    ->whereHas('ekstraksi', fn (Builder $e) => $e->where('status', StatusEkstraksi::TanpaTeks->value)))->count(),
            ],
            'tautan_tidak_dapat_diakses' => self::tautanDokumen()->where('status_cek', StatusCekTautan::TidakDapatDiakses->value)->count(),
            'ekstraksi' => [
                'gagal' => self::ekstraksi()->where('status', StatusEkstraksi::Gagal->value)->count(),
                'tidak_dapat_diunduh' => self::ekstraksi()->where('status', StatusEkstraksi::TidakDapatDiunduh->value)->count(),
            ],
            'isi_berubah' => self::ekstraksi()->where('isi_berubah_pada', '>=', $sejak)->count(),
            'relasi_tertunda' => [
                'jumlah' => (clone $relasiTertunda)->count(),
                'daftar' => $relasiTertunda->limit(self::BATAS_DAFTAR)->get()->map(fn (DokumenRelasi $r): string => sprintf(
                    '%s %s %s (efektif %s)',
                    $r->dokumen->sebutan_singkat,
                    mb_strtolower($r->jenis_relasi->label()),
                    $r->dokumenTerkait->sebutan_singkat,
                    $r->efektif_pada?->translatedFormat('d F Y') ?? '—',
                ))->values()->all(),
            ],
        ];
    }

    /**
     * @return array{jumlah: int, tertua: string|null}
     */
    private static function antrean(): array
    {
        $menunggu = Dokumen::query()->where('status_publikasi', StatusPublikasi::MenungguVerifikasi->value);
        $jumlah = (clone $menunggu)->count();

        if ($jumlah === 0) {
            return ['jumlah' => 0, 'tertua' => null];
        }

        // Waktu pengajuan terakhir tiap dokumen (riwayat publikasi; cadangan updated_at), lalu yang paling lama.
        $diajukan = RiwayatStatusDokumen::query()
            ->selectRaw('MAX(created_at)')
            ->whereColumn('riwayat_status_dokumen.dokumen_id', 'dokumen.id')
            ->where('jenis', JenisRiwayatStatus::Publikasi->value)
            ->where('ke', StatusPublikasi::MenungguVerifikasi->value);

        $tertua = $menunggu
            ->selectRaw('COALESCE(('.$diajukan->toSql().'), dokumen.updated_at) as diajukan_pada', $diajukan->getBindings())
            ->orderBy('diajukan_pada')
            ->value('diajukan_pada');

        return ['jumlah' => $jumlah, 'tertua' => $tertua === null ? null : CarbonImmutable::parse((string) $tertua)->toDateTimeString()];
    }

    /**
     * @return Builder<Dokumen>
     */
    private static function terbit(): Builder
    {
        return Dokumen::query()->where('status_publikasi', StatusPublikasi::Terbit->value);
    }

    /**
     * Tautan milik dokumen yang belum dihapus.
     *
     * @return Builder<TautanBerkas>
     */
    private static function tautanDokumen(): Builder
    {
        return TautanBerkas::query()->whereHasMorph('pemilik', [Dokumen::class]);
    }

    /**
     * @return Builder<EkstraksiTeks>
     */
    private static function ekstraksi(): Builder
    {
        return EkstraksiTeks::query()->whereHas('tautanBerkas', fn (Builder $t) => $t->whereHasMorph('pemilik', [Dokumen::class]));
    }
}
