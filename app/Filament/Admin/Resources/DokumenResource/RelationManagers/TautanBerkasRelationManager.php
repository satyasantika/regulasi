<?php

namespace App\Filament\Admin\Resources\DokumenResource\RelationManagers;

use App\Actions\Berkas\HapusTautanBerkas;
use App\Actions\Berkas\SimpanTautanBerkas;
use App\Contracts\PenyimpananBerkas;
use App\Enums\JenisTautan;
use App\Enums\Visibilitas;
use App\Models\Dokumen;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Rules\TautanBerkasValid;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Tautan berkas dokumen (US-DOK-02, US-DOK-04): isian URL + petunjuk berbagi, TANPA unggahan berkas.
 * Simpan/hapus lewat SimpanTautanBerkas/HapusTautanBerkas; otorisasi lewat TautanBerkasPolicy.
 */
class TautanBerkasRelationManager extends RelationManager
{
    protected static string $relationship = 'tautanBerkas';

    protected static ?string $title = 'Tautan berkas';

    protected static ?string $modelLabel = 'tautan berkas';

    protected static ?string $pluralModelLabel = 'Tautan berkas';

    public const string PETUNJUK_PUBLIK = 'Dokumen publik: di Google Drive pilih Bagikan → Akses umum "Siapa saja yang memiliki link" → Salin link.';

    public const string PETUNJUK_INTERNAL = 'Dokumen internal: bagikan terbatas ke domain unsil.ac.id (Akses umum "Universitas Siliwangi"), jangan "Siapa saja yang memiliki link".';

    public const string PETUNJUK_UMUM = 'Simpan berkas di Shared Drive "Regulasi FKIP", bukan Drive pribadi. Untuk peraturan pemerintah utamakan tautan sumber resmi (peraturan.go.id, jdih.*.go.id, peraturan.bpk.go.id). Tidak ada unggah berkas ke server.';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    protected function getCreateAuthorizationResponse(): Response
    {
        return (auth()->user()?->can('create', [TautanBerkas::class, $this->dokumen()]) ?? false) ? Response::allow() : Response::deny();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('url')
                    ->label('Tautan (URL)')
                    ->required()
                    ->maxLength(2048)
                    ->placeholder('https://drive.google.com/file/d/…/view')
                    ->rule(fn (Get $get): TautanBerkasValid => new TautanBerkasValid(self::jenis($get('jenis'))))
                    ->helperText(fn (): string => $this->petunjukBerbagi())
                    ->columnSpanFull(),
                Select::make('jenis')
                    ->label('Jenis')
                    ->options(JenisTautan::class)
                    ->default(fn (): string => $this->dokumen()->naskahUtama()->exists() ? JenisTautan::Lampiran->value : JenisTautan::NaskahUtama->value)
                    ->required()
                    ->live()
                    ->helperText('Hanya satu naskah utama: naskah utama baru menjadikan yang lama lampiran.'),
                TextInput::make('label')
                    ->label('Label')
                    ->maxLength(255)
                    ->placeholder('Mis. Naskah PDF, Lampiran I — Matriks Penilaian'),
                TextInput::make('urutan')
                    ->label('Urutan')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(65535)
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('ekstraksi'))
            ->columns([
                TextColumn::make('jenis')->label('Jenis')->badge(),
                TextColumn::make('label')->label('Label')->placeholder('—')->wrap(),
                TextColumn::make('penyedia')->label('Penyedia')->badge(),
                TextColumn::make('status_cek')->label('Status cek')->badge(),
                TextColumn::make('ekstraksi.status')
                    ->label('Ekstraksi')
                    ->badge()
                    ->placeholder('Belum')
                    ->tooltip(fn (TautanBerkas $record): ?string => $record->ekstraksi?->pesan),
                TextColumn::make('jumlah_dibuka')->label('Dibuka')->numeric(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah tautan')
                    ->using(function (array $data): TautanBerkas {
                        $hasil = app(SimpanTautanBerkas::class)->handle($this->dokumen(), $data, $this->pengguna());
                        $this->beriPeringatan($hasil['peringatan']);

                        return $hasil['tautan'];
                    }),
            ])
            ->recordActions([
                Action::make('buka')
                    ->label('Buka')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->authorize('buka')
                    ->url(fn (TautanBerkas $record): string => app(PenyimpananBerkas::class)->urlBuka($record))
                    ->openUrlInNewTab(),
                EditAction::make()
                    ->using(function (TautanBerkas $record, array $data): TautanBerkas {
                        $hasil = app(SimpanTautanBerkas::class)->handle($this->dokumen(), $data, $this->pengguna(), $record);
                        $this->beriPeringatan($hasil['peringatan']);

                        return $hasil['tautan'];
                    }),
                DeleteAction::make()
                    ->using(function (TautanBerkas $record): bool {
                        app(HapusTautanBerkas::class)->handle($record, $this->pengguna());

                        return true;
                    }),
            ]);
    }

    private static function jenis(mixed $nilai): ?JenisTautan
    {
        return $nilai instanceof JenisTautan ? $nilai : JenisTautan::tryFrom((string) $nilai);
    }

    private function petunjukBerbagi(): string
    {
        $petunjuk = $this->dokumen()->visibilitas === Visibilitas::Internal ? self::PETUNJUK_INTERNAL : self::PETUNJUK_PUBLIK;

        return $petunjuk.' '.self::PETUNJUK_UMUM;
    }

    private function beriPeringatan(?string $peringatan): void
    {
        if ($peringatan !== null) {
            Notification::make()->title('Perhatian')->body($peringatan)->warning()->send();
        }
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
