<?php

namespace App\Models;

use App\Enums\JenisTautan;
use App\Enums\StatusBerlaku;
use App\Enums\StatusEkstraksi;
use App\Enums\StatusPublikasi;
use App\Enums\Visibilitas;
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
use Illuminate\Support\Collection as KoleksiDasar;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Dokumen hukum/pendukung (03-SKEMA §3.7). Status publikasi & berlaku hanya diubah lewat Action (F5/F6),
 * karena itu tidak termasuk $fillable.
 *
 * Indeks pencarian (F7.2, Scout `{prefix}dokumen`): hanya dokumen terbit & tidak dihapus (BR-01). Sinkronisasi
 * otomatis per simpan dimatikan (searchIndexShouldBeUpdated); indeks diperbarui eksplisit lewat
 * sinkronkanIndeks() dari Action/pendengar setelah commit, sehingga perubahan pivot & ekstraksi ikut terbawa.
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

    /**
     * Relasi yang dimuat sebelum toSearchableArray (impor massal, job MakeSearchable).
     *
     * @var list<string>
     */
    public const array RELASI_INDEKS = ['jenisDokumen', 'badanPenetap', 'kategoriSistem', 'tag', 'tautanBerkas.ekstraksi'];

    public function searchableAs(): string
    {
        return config('scout.prefix').'dokumen';
    }

    public function shouldBeSearchable(): bool
    {
        return $this->status_publikasi === StatusPublikasi::Terbit && ! $this->trashed();
    }

    /**
     * Simpan biasa tidak memicu indeks; Action memanggil sinkronkanIndeks() setelah commit.
     */
    public function searchIndexShouldBeUpdated(): bool
    {
        return false;
    }

    public function wasSearchableBeforeUpdate(): bool
    {
        return $this->getOriginal('status_publikasi') === StatusPublikasi::Terbit;
    }

    public function wasSearchableBeforeDelete(): bool
    {
        return $this->getOriginal('status_publikasi') === StatusPublikasi::Terbit;
    }

    /**
     * Terbit → (ulang) indeks; selain itu → keluarkan dari indeks.
     */
    public function sinkronkanIndeks(): void
    {
        $this->shouldBeSearchable() ? $this->searchable() : $this->unsearchable();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query->with(self::RELASI_INDEKS);
    }

    /**
     * @param  KoleksiDasar<int, self>  $models
     * @return KoleksiDasar<int, self>
     */
    public function makeSearchableUsing(KoleksiDasar $models): KoleksiDasar
    {
        return $models->each(fn (self $dokumen) => $dokumen->loadMissing(self::RELASI_INDEKS));
    }

    /**
     * Dokumen di indeks Meilisearch (02-ARSITEKTUR §5.1).
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing(self::RELASI_INDEKS);

        return [
            'id' => $this->getKey(),
            'sebutan_singkat' => $this->sebutan_singkat,
            'sebutan' => $this->sebutan,
            'judul' => $this->judul,
            'nomor' => $this->nomor,
            'tahun' => $this->tahun,
            'jenis' => $this->jenisDokumen->kode,
            'jenis_nama' => $this->jenisDokumen->nama,
            'kelompok' => $this->jenisDokumen->kelompok->value,
            'badan_penetap' => $this->badanPenetap->slug,
            'badan_nama' => $this->badanPenetap->nama,
            'status_berlaku' => $this->status_berlaku->value,
            'visibilitas' => $this->visibilitas->value,
            'tanpa_berkas' => $this->tanpa_berkas,
            'kategori' => $this->kategoriSistem->pluck('kode')->values()->all(),
            'tag' => $this->tag->pluck('nama')->values()->all(),
            'abstrak' => $this->abstrak,
            'isi_teks' => $this->isiTeksIndeks(),
            'tanggal_penetapan_ts' => $this->tanggal_penetapan?->getTimestamp(),
            'dipublikasikan_pada_ts' => $this->dipublikasikan_pada?->getTimestamp(),
            'jumlah_dibuka' => $this->jumlah_dibuka,
            'url' => url("dokumen/{$this->getKey()}/{$this->slug}"),
        ];
    }

    /**
     * Gabungan teks ekstraksi berhasil (naskah utama lebih dulu), dipotong ke N kata pertama
     * (config regulasi.potong_isi_teks_kata, 02-ARSITEKTUR §5.1).
     */
    public function isiTeksIndeks(): ?string
    {
        $teks = $this->tautanBerkas
            ->map(fn (TautanBerkas $tautan): ?string => $tautan->ekstraksi?->status === StatusEkstraksi::Berhasil ? $tautan->ekstraksi->isi_teks : null)
            ->filter(fn (?string $isi): bool => filled($isi))
            ->implode("\n\n");

        if ($teks === '') {
            return null;
        }

        $batas = max(1, (int) config('regulasi.potong_isi_teks_kata', 60000));
        $kata = preg_split('/\s+/u', trim($teks), $batas + 1) ?: [];

        return count($kata) > $batas ? implode(' ', array_slice($kata, 0, $batas)) : $teks;
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
