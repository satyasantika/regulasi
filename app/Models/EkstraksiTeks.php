<?php

namespace App\Models;

use App\Enums\StatusEkstraksi;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Teks hasil ekstraksi PDF di balik satu tautan berkas (03-SKEMA §3.10a). Diisi oleh EkstrakTeksTautan.
 *
 * @property string $id
 * @property string $tautan_berkas_id
 * @property string|null $isi_teks
 * @property int|null $jumlah_karakter
 * @property int|null $jumlah_halaman
 * @property int|null $ukuran_unduhan
 * @property string|null $hash_sha256
 * @property StatusEkstraksi $status
 * @property int $percobaan
 * @property string|null $pesan
 * @property CarbonImmutable|null $diekstraksi_pada
 * @property CarbonImmutable|null $isi_berubah_pada
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read TautanBerkas $tautanBerkas
 */
class EkstraksiTeks extends Model
{
    use HasUuids;

    protected $table = 'ekstraksi_teks';

    protected $fillable = [
        'tautan_berkas_id', 'isi_teks', 'jumlah_karakter', 'jumlah_halaman', 'ukuran_unduhan', 'hash_sha256',
        'status', 'percobaan', 'pesan', 'diekstraksi_pada', 'isi_berubah_pada',
    ];

    protected $attributes = [
        'status' => 'belum',
        'percobaan' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusEkstraksi::class,
            'jumlah_karakter' => 'integer',
            'jumlah_halaman' => 'integer',
            'ukuran_unduhan' => 'integer',
            'percobaan' => 'integer',
            'diekstraksi_pada' => 'datetime',
            'isi_berubah_pada' => 'datetime',
        ];
    }

    /** @return BelongsTo<TautanBerkas, $this> */
    public function tautanBerkas(): BelongsTo
    {
        return $this->belongsTo(TautanBerkas::class, 'tautan_berkas_id')->withTrashed();
    }

    /**
     * Kembalikan ke keadaan awal (URL tautan berubah atau jenis berubah): teks & hash lama tidak berlaku lagi.
     */
    public function setelUlang(StatusEkstraksi $status = StatusEkstraksi::Belum): void
    {
        $this->forceFill([
            'isi_teks' => null,
            'jumlah_karakter' => null,
            'jumlah_halaman' => null,
            'ukuran_unduhan' => null,
            'hash_sha256' => null,
            'status' => $status,
            'percobaan' => 0,
            'pesan' => null,
            'diekstraksi_pada' => null,
            'isi_berubah_pada' => null,
        ]);
    }
}
