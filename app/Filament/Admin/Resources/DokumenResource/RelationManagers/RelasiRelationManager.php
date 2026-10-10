<?php

namespace App\Filament\Admin\Resources\DokumenResource\RelationManagers;

use App\Actions\Relasi\BuatDokumenRujukan;
use App\Actions\Relasi\HapusRelasiDokumen;
use App\Actions\Relasi\TambahRelasiDokumen;
use App\Enums\JenisRelasi;
use App\Filament\Admin\Resources\DokumenResource;
use App\Models\Dokumen;
use App\Models\DokumenRelasi;
use App\Models\User;
use App\Support\CacheRegulasi;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Relasi dokumen (US-REL-01, US-REL-02, BR-07..BR-11). Tambah lewat TambahRelasiDokumen, hapus berpasangan lewat
 * HapusRelasiDokumen dengan alasan wajib; tidak ada aksi ubah (hapus lalu tambah). Tombol aksi hanya untuk pemilik
 * relasi.kelola; pengguna lain yang boleh melihat dokumen hanya melihat tabelnya.
 */
class RelasiRelationManager extends RelationManager
{
    protected static string $relationship = 'relasi';

    protected static ?string $title = 'Relasi';

    protected static ?string $modelLabel = 'relasi';

    protected static ?string $pluralModelLabel = 'Relasi';

    public const int BATAS_HASIL_CARI = 50;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('dokumenTerkait'))
            ->defaultSort('created_at')
            ->emptyStateHeading('Belum ada relasi')
            ->emptyStateDescription('Catat bila dokumen ini mencabut, mengubah, atau melaksanakan dokumen lain (atau sebaliknya).')
            ->columns([
                TextColumn::make('jenis_relasi')->label('Relasi')->badge(),
                TextColumn::make('dokumenTerkait.sebutan_singkat')
                    ->label('Dokumen terkait')
                    ->description(fn (DokumenRelasi $record): string => Str::limit($record->dokumenTerkait->judul, 100))
                    ->url(fn (DokumenRelasi $record): string => DokumenResource::getUrl('view', ['record' => $record->dokumen_terkait_id]))
                    ->wrap(),
                TextColumn::make('dokumenTerkait.status_berlaku')->label('Status dokumen terkait')->badge(),
                IconColumn::make('efektif')->label('Efektif')->boolean(),
                TextColumn::make('efektif_pada')->label('Efektif pada')->date('d F Y')->placeholder('—'),
                TextColumn::make('keterangan')->label('Keterangan')->wrap()->placeholder('—'),
            ])
            ->headerActions([
                $this->aksiTambahRelasi(),
                $this->aksiTambahRujukan(),
            ])
            ->recordActions([
                Action::make('hapusRelasi')
                    ->label('Hapus')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->visible(fn (DokumenRelasi $record): bool => $this->pengguna()->can('delete', $record))
                    ->modalHeading('Hapus relasi')
                    ->modalDescription('Relasi pasangannya di dokumen terkait ikut dihapus dan status berlaku dihitung ulang.')
                    ->modalSubmitActionLabel('Hapus relasi')
                    ->schema([
                        Textarea::make('alasan')
                            ->label('Alasan penghapusan (wajib)')
                            ->required()
                            ->minLength(HapusRelasiDokumen::MINIMUM_ALASAN)
                            ->maxLength(1000)
                            ->rows(3),
                    ])
                    ->action(function (DokumenRelasi $record, array $data, Action $action): void {
                        $this->jalankan($action, fn (User $u) => app(HapusRelasiDokumen::class)->handle($record, $u, (string) $data['alasan']), 'Relasi dihapus');
                    }),
            ]);
    }

    private function aksiTambahRelasi(): Action
    {
        return Action::make('tambahRelasi')
            ->label('Tambah relasi')
            ->icon(Heroicon::OutlinedLink)
            ->visible(fn (): bool => $this->pengguna()->can('create', DokumenRelasi::class))
            ->modalHeading('Tambah relasi')
            ->modalSubmitActionLabel('Simpan relasi')
            ->schema([
                Select::make('jenis_relasi')
                    ->label('Jenis relasi')
                    ->options(self::opsiJenis())
                    ->required(),
                Select::make('dokumen_terkait_id')
                    ->label('Dokumen terkait')
                    ->searchable()
                    ->required()
                    ->getSearchResultsUsing(fn (string $search): array => $this->cariDokumen($search))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => self::labelDokumen(Dokumen::query()->find($value)))
                    ->helperText('Cari sebutan, nomor, atau judul. Peraturan yang belum ada? Gunakan "Tambah dokumen rujukan".'),
                Textarea::make('keterangan')
                    ->label('Keterangan (opsional)')
                    ->placeholder('Mis. untuk lingkungan Kemendiktisaintek; mencabut Pasal 5')
                    ->maxLength(TambahRelasiDokumen::MAKS_KETERANGAN)
                    ->rows(2),
            ])
            ->action(function (array $data, Action $action): void {
                $this->jalankan($action, fn (User $u) => app(TambahRelasiDokumen::class)->handle(
                    $this->dokumen(),
                    Dokumen::query()->findOrFail($data['dokumen_terkait_id']),
                    JenisRelasi::from((string) $data['jenis_relasi']),
                    filled($data['keterangan'] ?? null) ? (string) $data['keterangan'] : null,
                    $u,
                ), 'Relasi ditambahkan');
            });
    }

    /**
     * Pintas BR-11: dokumen rujukan (metadata saja) langsung terbit, opsional langsung dihubungkan.
     */
    private function aksiTambahRujukan(): Action
    {
        return Action::make('tambahRujukan')
            ->label('Tambah dokumen rujukan')
            ->icon(Heroicon::OutlinedDocumentPlus)
            ->color('gray')
            ->visible(fn (): bool => $this->pengguna()->can('create', DokumenRelasi::class)
                && $this->pengguna()->can('create', Dokumen::class)
                && $this->pengguna()->can('verifikasi', $this->dokumen()))
            ->modalHeading('Tambah dokumen rujukan')
            ->modalDescription('Untuk peraturan yang belum ada di repositori: metadata saja, tanpa berkas, langsung terbit (BR-11).')
            ->modalSubmitActionLabel('Buat & terbitkan')
            ->schema([
                Select::make('jenis_dokumen_id')
                    ->label('Jenis dokumen')
                    ->options(fn (): array => CacheRegulasi::master('jenis-dokumen')->pluck('nama', 'id')->all())
                    ->searchable()
                    ->required(),
                TextInput::make('nomor')->label('Nomor')->maxLength(100),
                TextInput::make('tahun')->label('Tahun')->numeric()->integer()->minValue(1945)->maxValue((int) date('Y') + 1)->required(),
                Select::make('badan_penetap_id')
                    ->label('Badan penetap (T.E.U.)')
                    ->options(fn (): array => CacheRegulasi::master('badan-penetap')->pluck('nama', 'id')->all())
                    ->searchable()
                    ->required(),
                Textarea::make('judul')->label('Judul (tentang)')->required()->maxLength(1000)->rows(2),
                Select::make('kategori_sistem')
                    ->label('Kategori sistem')
                    ->multiple()
                    ->options(fn (): array => DokumenResource::opsiKategori())
                    ->required(),
                Select::make('jenis_relasi')
                    ->label('Langsung hubungkan (opsional)')
                    ->options(self::opsiJenis())
                    ->placeholder('Jangan hubungkan sekarang'),
            ])
            ->action(function (array $data, Action $action): void {
                $this->jalankan($action, function (User $u) use ($data): void {
                    $rujukan = app(BuatDokumenRujukan::class)->handle(Arr::except($data, ['jenis_relasi']), $u);

                    if (filled($data['jenis_relasi'] ?? null)) {
                        app(TambahRelasiDokumen::class)->handle($this->dokumen(), $rujukan, JenisRelasi::from((string) $data['jenis_relasi']), null, $u);
                    }
                }, 'Dokumen rujukan dibuat dan diterbitkan');
            });
    }

    /**
     * @return array<string, string>
     */
    public static function opsiJenis(): array
    {
        $opsi = [];

        foreach (JenisRelasi::cases() as $jenis) {
            $opsi[$jenis->value] = $jenis->labelSisiIni();
        }

        return $opsi;
    }

    /**
     * Pencarian dokumen terkait di sisi server (sebutan singkat/nomor/judul), tanpa dokumen ini sendiri.
     *
     * @return array<string, string>
     */
    public function cariDokumen(string $cari): array
    {
        $cari = trim($cari);

        return Dokumen::query()
            ->whereKeyNot($this->dokumen()->getKey())
            ->when($cari !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('sebutan_singkat', 'like', "%{$cari}%")
                ->orWhere('nomor', 'like', "{$cari}%")
                ->orWhere('judul', 'like', "%{$cari}%")))
            ->orderByDesc('tahun')
            ->orderBy('sebutan_singkat')
            ->limit(self::BATAS_HASIL_CARI)
            ->get(['id', 'sebutan_singkat', 'judul'])
            ->mapWithKeys(fn (Dokumen $d): array => [$d->getKey() => (string) self::labelDokumen($d)])
            ->all();
    }

    public static function labelDokumen(?Dokumen $dokumen): ?string
    {
        return $dokumen === null ? null : $dokumen->sebutan_singkat.' — '.Str::limit($dokumen->judul, 90);
    }

    /**
     * Jalankan Action domain; galat validasi/otorisasi/kunci tampil sebagai notifikasi dan modal tetap terbuka.
     *
     * @param  callable(User): mixed  $aksi
     */
    private function jalankan(Action $action, callable $aksi, string $sukses): void
    {
        try {
            $aksi($this->pengguna());
        } catch (ValidationException|AuthorizationException|LockTimeoutException $e) {
            Notification::make()
                ->title('Gagal')
                ->body(match (true) {
                    $e instanceof ValidationException => implode(' ', Arr::flatten($e->errors())),
                    $e instanceof LockTimeoutException => 'Dokumen sedang diproses pengguna lain. Coba lagi sebentar lagi.',
                    default => 'Anda tidak berwenang melakukan tindakan ini.',
                })
                ->danger()
                ->send();

            $action->halt();
        }

        Notification::make()->title($sukses)->success()->send();
        $this->dokumen()->refresh();
    }

    private function dokumen(): Dokumen
    {
        /** @var Dokumen $dokumen */
        $dokumen = $this->getOwnerRecord();

        return $dokumen;
    }

    private function pengguna(): User
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();

        return $pengguna;
    }
}
