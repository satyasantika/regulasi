<?php

namespace App\Models;

use App\Enums\JenisTautan;
use App\Enums\StatusBerlaku;
use App\Enums\StatusPublikasi;
use App\Enums\Visibilitas;
use App\Jobs\BangunKamusIstilah;
use Carbon\CarbonImmutable;
use Database\Factories\DokumenFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Attributes\SearchUsingFullText;
use Laravel\Scout\Attributes\SearchUsingPrefix;
use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Dokumen hukum/pendukung (03-SKEMA §3.7). Status publikasi & berlaku hanya diubah lewat Action (F5/F6),
 * karena itu tidak termasuk $fillable.
 *
 * Pencarian (F7.2, Scout driver `database` + FULLTEXT MariaDB): hanya dokumen terbit & tidak dihapus (BR-01).
 * Tidak ada indeks terpisah; sinkronkanIndeks() dipanggil Action/pendengar setelah commit untuk membangun ulang
 * kamus istilah (koreksi salah ketik).
 *
 * @property string $id
 * @property string $slug
 * @property string $jenis_dokumen_id
 * @property string $badan_penetap_id
 * @property string|null $nomor
 * @property int $tahun
 * @property string $judul
 * @property string $sebutan
 * @property string $sebutan_singkat
 * @property string|null $tempat_penetapan
 * @property CarbonImmutable|null $tanggal_penetapan
 * @property CarbonImmutable|null $tanggal_pengundangan
 * @property CarbonImmutable|null $tanggal_berlaku
 * @property string|null $sumber
 * @property string $bahasa
 * @property string|null $abstrak
 * @property string|null $catatan
 * @property StatusBerlaku $status_berlaku
 * @property bool $status_berlaku_manual
 * @property StatusPublikasi $status_publikasi
 * @property Visibilitas $visibilitas
 * @property bool $tanpa_berkas
 * @property int $jumlah_dibuka
 * @property string|null $catatan_verifikasi
 * @property string|null $dibuat_oleh
 * @property string|null $diverifikasi_oleh
 * @property CarbonImmutable|null $diverifikasi_pada
 * @property CarbonImmutable|null $dipublikasikan_pada
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read CarbonImmutable|null $tanggal_efektif
 * @property-read JenisDokumen $jenisDokumen
 * @property-read BadanPenetap $badanPenetap
 * @property-read Collection<int, TautanBerkas> $tautanBerkas
 * @property-read TautanBerkas|null $naskahUtama
 * @property-read Collection<int, RiwayatStatusDokumen> $riwayatStatus
 * @property-read Collection<int, DokumenRelasi> $relasi
 * @property-read Collection<int, KategoriSistem> $kategori
 * @property-read Collection<int, TautanBerkas> $tautan
 */
class Dokumen extends Model
{
    /** @use HasFactory<DokumenFactory> */
    use HasFactory, HasUuids, LogsActivity, Searchable, SoftDeletes;

    protected $table = 'dokumen';

    protected $fillable = [
        'slug', 'jenis_dokumen_id', 'badan_penetap_id', 'nomor', 'tahun', 'judul', 'sebutan', 'sebutan_singkat',
        'tempat_penetapan', 'tanggal_penetapan', 'tanggal_pengundangan', 'tanggal_berlaku', 'sumber', 'bahasa',
        'abstrak', 'catatan', 'visibilitas', 'tanpa_berkas', 'dibuat_oleh',
    ];

    protected $attributes = [
        'bahasa' => 'id',
        'status_berlaku' => 'berlaku',
        'status_berlaku_manual' => false,
        'status_publikasi' => 'draf',
        'visibilitas' => 'publik',
        'tanpa_berkas' => false,
        'jumlah_dibuka' => 0,
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'tanggal_penetapan' => 'date',
            'tanggal_pengundangan' => 'date',
            'tanggal_berlaku' => 'date',
            'status_berlaku' => StatusBerlaku::class,
            'status_berlaku_manual' => 'boolean',
            'status_publikasi' => StatusPublikasi::class,
            'visibilitas' => Visibilitas::class,
            'tanpa_berkas' => 'boolean',
            'jumlah_dibuka' => 'integer',
            'diverifikasi_pada' => 'datetime',
            'dipublikasikan_pada' => 'datetime',
        ];
    }

    /**
     * Jejak audit: kolom isian + kolom status; penghitung jumlah_dibuka tidak dicatat.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnly(['status_publikasi', 'status_berlaku', 'status_berlaku_manual', 'catatan_verifikasi'])
            ->logExcept(['jumlah_dibuka'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('dokumen');
    }

    public function searchableAs(): string
    {
        return config('scout.prefix').'dokumen';
    }

    public function shouldBeSearchable(): bool
    {
        return $this->status_publikasi === StatusPublikasi::Terbit && ! $this->trashed();
    }

    /**
     * Simpan biasa tidak memicu Scout; Action memanggil sinkronkanIndeks() setelah commit.
     */
    public function searchIndexShouldBeUpdated(): bool
    {
        return false;
    }

    /**
     * Dengan Scout driver `database` indeks adalah tabel `dokumen` sendiri (FULLTEXT), sehingga tidak ada yang
     * disinkronkan; yang perlu diperbarui adalah kamus istilah (koreksi salah ketik), dibangun ulang di antrean `berat`
     * setelah commit. Pemicu beruntun digabung (BangunKamusIstilah unik).
     */
    public function sinkronkanIndeks(): void
    {
        BangunKamusIstilah::dispatch()->afterCommit();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query->where('status_publikasi', StatusPublikasi::Terbit->value);
    }

    /**
     * Kolom yang dicari Scout driver `database` (02-ARSITEKTUR §5.1): FULLTEXT ft_dokumen pada judul, sebutan
     * singkat, dan abstrak; awalan untuk id dan nomor. Teks hasil ekstraksi dicari lewat relasi di PencarianDatabase.
     *
     * @return array<string, mixed>
     */
    #[SearchUsingFullText(['judul', 'sebutan_singkat', 'abstrak'])]
    #[SearchUsingPrefix(['id', 'nomor'])]
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->getKey(),
            'sebutan_singkat' => $this->sebutan_singkat,
            'judul' => $this->judul,
            'nomor' => $this->nomor,
            'abstrak' => $this->abstrak,
        ];
    }

    /**
     * Pencarian Scout terbatas pada dokumen terbit (driver database tidak memanggil shouldBeSearchable saat mencari).
     *
     * @return ScoutBuilder<static>
     */
    public static function cariTerbit(string $kueri): ScoutBuilder
    {
        return static::search($kueri)->query(fn (Builder $q) => $q->where('dokumen.status_publikasi', StatusPublikasi::Terbit->value));
    }

    /** @return BelongsTo<JenisDokumen, $this> */
    public function jenisDokumen(): BelongsTo
    {
        return $this->belongsTo(JenisDokumen::class, 'jenis_dokumen_id')->withTrashed();
    }

    /** @return BelongsTo<BadanPenetap, $this> */
    public function badanPenetap(): BelongsTo
    {
        return $this->belongsTo(BadanPenetap::class, 'badan_penetap_id')->withTrashed();
    }

    /** @return BelongsToMany<KategoriSistem, $this> */
    public function kategoriSistem(): BelongsToMany
    {
        return $this->belongsToMany(KategoriSistem::class, 'dokumen_kategori_sistem');
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tag(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'dokumen_tag');
    }

    /** @return BelongsTo<User, $this> */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /** @return BelongsTo<User, $this> */
    public function pemverifikasi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    /**
     * Tautan berkas aktif, naskah utama lebih dulu lalu menurut urutan.
     *
     * @return MorphMany<TautanBerkas, $this>
     */
    public function tautanBerkas(): MorphMany
    {
        return $this->morphMany(TautanBerkas::class, 'pemilik')
            ->orderByRaw('CASE WHEN jenis = ? THEN 0 ELSE 1 END', [JenisTautan::NaskahUtama->value])
            ->orderBy('urutan')
            ->orderBy('created_at');
    }

    /**
     * Alias relasi untuk JSON:API v1 (`include=kategori`, F9.2).
     *
     * @return BelongsToMany<KategoriSistem, $this>
     */
    public function kategori(): BelongsToMany
    {
        return $this->kategoriSistem();
    }

    /**
     * Alias relasi untuk JSON:API v1 (`include=tautan`, F9.2); dokumen pemilik langsung terpasang (chaperone)
     * agar tautan buka dapat dibentuk tanpa kueri tambahan.
     *
     * @return MorphMany<TautanBerkas, $this>
     */
    public function tautan(): MorphMany
    {
        return $this->tautanBerkas()->chaperone('pemilik');
    }

    /** @return MorphOne<TautanBerkas, $this> */
    public function naskahUtama(): MorphOne
    {
        return $this->morphOne(TautanBerkas::class, 'pemilik')->where('jenis', JenisTautan::NaskahUtama->value);
    }

    /**
     * Riwayat status (append-only), terbaru lebih dulu.
     *
     * @return HasMany<RiwayatStatusDokumen, $this>
     */
    public function riwayatStatus(): HasMany
    {
        return $this->hasMany(RiwayatStatusDokumen::class, 'dokumen_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * Relasi milik dokumen ini (sisi `dokumen_id`), termasuk baris kebalikan (BR-07).
     *
     * @return HasMany<DokumenRelasi, $this>
     */
    public function relasi(): HasMany
    {
        return $this->hasMany(DokumenRelasi::class, 'dokumen_id');
    }

    /**
     * Relasi yang dimiliki dokumen ini sebagai sumber berdampak (BR-08): sumber terbit dan tanggal efektif
     * (bila ada) sudah tiba.
     */
    public function efektifSebagaiSumber(?CarbonImmutable $hariIni = null): bool
    {
        $hariIni ??= CarbonImmutable::today();
        $tanggal = $this->tanggal_efektif;

        return $this->status_publikasi === StatusPublikasi::Terbit
            && ($tanggal === null || $tanggal->startOfDay()->lte($hariIni->startOfDay()));
    }

    /**
     * Tanggal efektif (BR-08): berlaku ?? pengundangan ?? penetapan.
     *
     * @return Attribute<CarbonImmutable|null, never>
     */
    protected function tanggalEfektif(): Attribute
    {
        return Attribute::get(fn (): ?CarbonImmutable => $this->tanggal_berlaku ?? $this->tanggal_pengundangan ?? $this->tanggal_penetapan)
            ->withoutObjectCaching();
    }

    public function milik(User $user): bool
    {
        return $this->dibuat_oleh !== null && $this->dibuat_oleh === $user->getKey();
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function terbit(Builder $query): void
    {
        $query->where('status_publikasi', StatusPublikasi::Terbit->value);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function publik(Builder $query): void
    {
        $query->where('visibilitas', Visibilitas::Publik->value);
    }

    /**
     * Visibilitas portal (BR-01, BR-02): tamu → terbit & publik; pengguna login → terbit (publik + internal).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function terlihatOleh(Builder $query, ?User $user): void
    {
        $query->where('status_publikasi', StatusPublikasi::Terbit->value);

        if ($user === null) {
            $query->where('visibilitas', Visibilitas::Publik->value);
        }
    }

    /**
     * Dokumen yang memiliki minimal satu kategori sistem yang ditugaskan kepada pengguna (BR-03).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function dalamKategoriPengguna(Builder $query, User $user): void
    {
        $query->whereHas('kategoriSistem', fn (Builder $q) => $q->whereIn(
            'kategori_sistem.id',
            fn ($sub) => $sub->select('kategori_sistem_id')->from('kategori_sistem_user')->where('user_id', $user->getKey()),
        ));
    }
}
