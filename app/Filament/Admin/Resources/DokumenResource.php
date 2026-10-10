<?php

namespace App\Filament\Admin\Resources;

use App\Enums\Peran;
use App\Enums\StatusBerlaku;
use App\Enums\StatusPublikasi;
use App\Enums\Visibilitas;
use App\Filament\Admin\Resources\DokumenResource\Pages\CreateDokumen;
use App\Filament\Admin\Resources\DokumenResource\Pages\EditDokumen;
use App\Filament\Admin\Resources\DokumenResource\Pages\ListDokumen;
use App\Filament\Admin\Resources\DokumenResource\Pages\ViewDokumen;
use App\Models\BadanPenetap;
use App\Models\Dokumen;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use App\Models\Tag;
use App\Models\User;
use App\Support\CacheRegulasi;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Dokumen hukum/pendukung (US-DOK-01). Penyimpanan lewat SimpanDokumen; status publikasi & berlaku
 * tidak dapat diubah dari form (hanya aksi F5/F6). Kontributor dibatasi kategori (BR-03) lewat query & Policy.
 */
class DokumenResource extends Resource
{
    protected static ?string $model = Dokumen::class;

    protected static ?string $slug = 'dokumen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Dokumen';

    protected static ?string $navigationLabel = 'Dokumen';

    protected static ?string $modelLabel = 'dokumen';

    protected static ?string $pluralModelLabel = 'Dokumen';

    protected static ?string $recordTitleAttribute = 'sebutan_singkat';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Identitas')
                    ->columns(2)
                    ->schema([
                        Select::make('jenis_dokumen_id')
                            ->label('Jenis dokumen')
                            ->options(fn (?Dokumen $record): array => static::opsiMaster('jenis-dokumen', $record?->jenis_dokumen_id))
                            ->searchable()
                            ->required()
                            ->live(),
                        TextInput::make('nomor')
                            ->label('Nomor')
                            ->maxLength(100)
                            ->required(fn (Get $get): bool => static::jenisWajibNomor($get('jenis_dokumen_id')))
                            ->helperText('Tulis persis seperti di naskah, mis. 181/PMK.06/2016.'),
                        TextInput::make('tahun')
                            ->label('Tahun')
                            ->numeric()
                            ->integer()
                            ->minValue(1945)
                            ->maxValue((int) date('Y') + 1)
                            ->required(),
                        Select::make('badan_penetap_id')
                            ->label('Badan penetap (T.E.U.)')
                            ->options(fn (?Dokumen $record): array => static::opsiMaster('badan-penetap', $record?->badan_penetap_id))
                            ->searchable()
                            ->required(),
                        Textarea::make('judul')
                            ->label('Judul (tentang)')
                            ->required()
                            ->maxLength(1000)
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
                Section::make('Penetapan & Keberlakuan')
                    ->columns(2)
                    ->schema([
                        TextInput::make('tempat_penetapan')->label('Tempat penetapan')->maxLength(100),
                        DatePicker::make('tanggal_penetapan')->label('Tanggal penetapan')->live(),
                        DatePicker::make('tanggal_pengundangan')
                            ->label('Tanggal pengundangan')
                            ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                $penetapan = $get('tanggal_penetapan');

                                if (filled($value) && filled($penetapan) && Carbon::parse((string) $value)->lt(Carbon::parse((string) $penetapan))) {
                                    $fail('Tanggal pengundangan tidak boleh sebelum tanggal penetapan.');
                                }
                            }),
                        DatePicker::make('tanggal_berlaku')->label('Tanggal mulai berlaku'),
                        TextInput::make('sumber')
                            ->label('Sumber')
                            ->maxLength(255)
                            ->placeholder('LN/BN/TLN, atau asal naskah')
                            ->columnSpanFull(),
                    ]),
                Section::make('Klasifikasi')
                    ->columns(2)
                    ->schema([
                        Select::make('kategori_sistem')
                            ->label('Kategori sistem')
                            ->multiple()
                            ->options(fn (): array => static::opsiKategori())
                            ->required()
                            ->helperText(fn (): ?string => static::adalahAdmin() ? null : 'Hanya kategori yang ditugaskan kepada Anda.'),
                        Select::make('tag')
                            ->label('Subjek/tag')
                            ->multiple()
                            ->searchable()
                            ->options(fn (): array => Tag::query()->terurut()->pluck('nama', 'id')->all())
                            ->createOptionForm(fn (): ?array => (auth()->user()?->can('tag.buat') ?? false) ? [
                                TextInput::make('nama')->label('Nama tag')->required()->maxLength(100),
                            ] : null)
                            ->createOptionUsing(function (array $data): string {
                                abort_unless(auth()->user()?->can('tag.buat') ?? false, 403);

                                return Tag::query()->firstOrCreate(['nama' => trim((string) $data['nama'])])->getKey();
                            }),
                        Radio::make('visibilitas')
                            ->label('Visibilitas')
                            ->options(Visibilitas::class)
                            ->default(Visibilitas::Publik->value)
                            ->inline()
                            ->required()
                            ->helperText('Internal: hanya pengguna yang masuk. Berkas dokumen internal dibagikan terbatas ke domain unsil.ac.id.'),
                    ]),
                Section::make('Abstrak')
                    ->schema([
                        Textarea::make('abstrak')
                            ->label('Abstrak')
                            ->maxLength(5000)
                            ->rows(6)
                            ->helperText('Teks polos, maks. 5000 karakter.'),
                    ]),
                Section::make('Sebutan')
                    ->columns(2)
                    ->description('Terisi otomatis dari jenis, badan, nomor, dan tahun. Kosongkan untuk dibentuk ulang.')
                    ->schema([
                        TextInput::make('sebutan')->label('Sebutan')->maxLength(500),
                        TextInput::make('sebutan_singkat')->label('Sebutan singkat')->maxLength(150),
                    ])
                    ->disabled(fn (): bool => ! static::adalahAdmin())
                    ->hiddenOn('create'),
                Section::make('Catatan internal')
                    ->schema([
                        Textarea::make('catatan')->label('Catatan internal')->rows(3)->maxLength(5000)
                            ->helperText('Tidak tampil di portal publik.'),
                    ])
                    ->visible(fn (): bool => static::adalahAdmin()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['jenisDokumen', 'kategoriSistem']))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('sebutan_singkat')->label('Sebutan')->searchable()->sortable(),
                TextColumn::make('judul')->label('Judul')->limit(80)->searchable()->wrap(),
                TextColumn::make('jenisDokumen.nama')->label('Jenis')->toggleable(),
                TextColumn::make('tahun')->label('Tahun')->sortable(),
                TextColumn::make('status_berlaku')->label('Status berlaku')->badge(),
                TextColumn::make('status_publikasi')->label('Publikasi')->badge(),
                IconColumn::make('visibilitas')->label('Visibilitas'),
                TextColumn::make('kategoriSistem.nama')->label('Kategori')->badge()->color('gray')->toggleable(),
                TextColumn::make('updated_at')->label('Diperbarui')->dateTime('d F Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('jenis_dokumen_id')->label('Jenis')
                    ->options(fn (): array => JenisDokumen::query()->terurut()->pluck('nama', 'id')->all()),
                SelectFilter::make('tahun')->label('Tahun')
                    ->options(fn (): array => Dokumen::query()->distinct()->orderByDesc('tahun')->pluck('tahun', 'tahun')->all()),
                SelectFilter::make('status_publikasi')->label('Status publikasi')->options(StatusPublikasi::class),
                SelectFilter::make('status_berlaku')->label('Status berlaku')->options(StatusBerlaku::class),
                SelectFilter::make('kategori')->label('Kategori')
                    ->options(fn (): array => KategoriSistem::query()->terurut()->pluck('nama', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('kategoriSistem', fn (Builder $q) => $q->whereKey($data['value']))
                        : $query),
                SelectFilter::make('badan_penetap_id')->label('Badan penetap')
                    ->options(fn (): array => BadanPenetap::query()->terurut()->pluck('nama', 'id')->all()),
                SelectFilter::make('visibilitas')->label('Visibilitas')->options(Visibilitas::class),
                TrashedFilter::make()->visible(fn (): bool => static::adalahSuperAdmin()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    /**
     * Kontributor hanya melihat dokumen berkategori yang ditugaskan (BR-03); dokumen terhapus lunak
     * hanya untuk super-admin (BR-15).
     *
     * @return Builder<Dokumen>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Dokumen> $query */
        $query = parent::getEloquentQuery();
        /** @var User|null $pengguna */
        $pengguna = auth()->user();

        if (static::adalahSuperAdmin()) {
            $query->withoutGlobalScopes([SoftDeletingScope::class]);
        }

        if ($pengguna !== null && ! $pengguna->can('dokumen.lihat-semua')) {
            $query->dalamKategoriPengguna($pengguna);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDokumen::route('/'),
            'create' => CreateDokumen::route('/create'),
            'view' => ViewDokumen::route('/{record}'),
            'edit' => EditDokumen::route('/{record}/edit'),
        ];
    }

    public static function adalahAdmin(): bool
    {
        return auth()->user()?->can('dokumen.lihat-semua') ?? false;
    }

    public static function adalahSuperAdmin(): bool
    {
        /** @var User|null $pengguna */
        $pengguna = auth()->user();

        return $pengguna?->hasRole(Peran::SuperAdmin->value) ?? false;
    }

    /**
     * Opsi kategori: admin semua kategori aktif; kontributor hanya yang ditugaskan.
     *
     * @return array<string, string>
     */
    public static function opsiKategori(): array
    {
        if (static::adalahAdmin()) {
            return CacheRegulasi::master('kategori-sistem')->pluck('nama', 'id')->all();
        }

        /** @var User|null $pengguna */
        $pengguna = auth()->user();

        return $pengguna?->kategoriSistem()->orderBy('urutan')->pluck('nama', 'kategori_sistem.id')->all() ?? [];
    }

    /**
     * Opsi master aktif dari cache; nilai tersimpan yang sudah nonaktif tetap ditampilkan.
     *
     * @return array<string, string>
     */
    protected static function opsiMaster(string $nama, ?string $terpilih): array
    {
        $opsi = CacheRegulasi::master($nama)->pluck('nama', 'id')->all();

        if ($terpilih !== null && ! array_key_exists($terpilih, $opsi)) {
            $model = CacheRegulasi::MASTER[$nama];
            $rekaman = $model::withTrashed()->find($terpilih);

            if ($rekaman !== null) {
                $opsi[$terpilih] = (string) $rekaman->getAttribute('nama');
            }
        }

        return $opsi;
    }

    protected static function jenisWajibNomor(mixed $jenisId): bool
    {
        if (blank($jenisId)) {
            return false;
        }

        $jenis = CacheRegulasi::master('jenis-dokumen')->firstWhere('id', $jenisId);

        return $jenis instanceof JenisDokumen && $jenis->wajib_nomor;
    }
}
