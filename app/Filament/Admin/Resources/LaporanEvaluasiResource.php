<?php

namespace App\Filament\Admin\Resources;

use App\Actions\Laporan\FinalkanLaporan;
use App\Actions\Laporan\HitungRingkasanLaporan;
use App\Filament\Admin\Resources\LaporanEvaluasiResource\Pages\CreateLaporanEvaluasi;
use App\Filament\Admin\Resources\LaporanEvaluasiResource\Pages\EditLaporanEvaluasi;
use App\Filament\Admin\Resources\LaporanEvaluasiResource\Pages\ListLaporanEvaluasi;
use App\Filament\Admin\Resources\LaporanEvaluasiResource\Pages\ViewLaporanEvaluasi;
use App\Models\LaporanEvaluasi;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Laporan evaluasi periode (F11.4, US-LAP-03; 03-SKEMA §3.15). Otorisasi lewat LaporanEvaluasiPolicy; laporan
 * final terkunci. PDF dirender dari snapshot ringkasan dan di-stream (tidak disimpan).
 */
class LaporanEvaluasiResource extends Resource
{
    protected static ?string $model = LaporanEvaluasi::class;

    protected static ?string $slug = 'laporan-evaluasi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?string $navigationLabel = 'Laporan Evaluasi';

    protected static ?string $modelLabel = 'laporan evaluasi';

    protected static ?string $pluralModelLabel = 'Laporan Evaluasi';

    protected static ?string $recordTitleAttribute = 'judul';

    protected static ?int $navigationSort = 60;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('judul')
                    ->label('Judul')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Laporan Evaluasi Semester Ganjil 2026/2027')
                    ->columnSpanFull(),
                DatePicker::make('periode_mulai')
                    ->label('Periode mulai')
                    ->required()
                    ->native(false)
                    ->displayFormat('d F Y'),
                DatePicker::make('periode_selesai')
                    ->label('Periode selesai')
                    ->required()
                    ->native(false)
                    ->displayFormat('d F Y')
                    ->afterOrEqual('periode_mulai'),
                Textarea::make('analisis')
                    ->label('Kendala / temuan (Bab V)')
                    ->rows(6)
                    ->maxLength(20000)
                    ->columnSpanFull(),
                Textarea::make('tindak_lanjut')
                    ->label('Rencana tindak lanjut (Bab VI)')
                    ->rows(6)
                    ->maxLength(20000)
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $nilai = fn (LaporanEvaluasi $record, string $kunci): mixed => data_get($record->ringkasan, $kunci);
        $persen = fn (mixed $n): string => $n === null ? '—' : $n.'%';

        return $schema
            ->columns(3)
            ->components([
                Section::make('Laporan')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('judul')->label('Judul')->columnSpanFull(),
                        TextEntry::make('periode')
                            ->label('Periode')
                            ->state(fn (LaporanEvaluasi $record): string => $record->periode_mulai->translatedFormat('d F Y').' – '.$record->periode_selesai->translatedFormat('d F Y')),
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('difinalkan_pada')->label('Difinalkan')->dateTime('d F Y H:i')->placeholder('—'),
                        TextEntry::make('dihitung_pada')
                            ->label('Ringkasan dihitung')
                            ->state(fn (LaporanEvaluasi $record): string => filled($t = $nilai($record, 'dihitung_pada')) ? CarbonImmutable::parse((string) $t)->translatedFormat('d F Y H:i') : '—'),
                        TextEntry::make('pembuat.name')->label('Dibuat oleh')->placeholder('—'),
                    ]),
                Section::make('Ringkasan (snapshot)')
                    ->columns(['default' => 2, 'md' => 4])
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('r_total')->label('Dokumen terbit')->state(fn (LaporanEvaluasi $r): string => (string) ($nilai($r, 'koleksi.total_terbit') ?? 0)),
                        TextEntry::make('r_tambah')->label('Penambahan periode')->state(fn (LaporanEvaluasi $r): string => (string) ($nilai($r, 'penambahan.jumlah') ?? 0)),
                        TextEntry::make('r_cabut')->label('Dicabut / diubah periode')->state(fn (LaporanEvaluasi $r): string => ($nilai($r, 'perubahan_status.dicabut') ?? 0).' / '.($nilai($r, 'perubahan_status.diubah') ?? 0)),
                        TextEntry::make('r_buka')->label('Tautan dibuka (web / API)')->state(fn (LaporanEvaluasi $r): string => ($nilai($r, 'pembukaan.web') ?? 0).' / '.($nilai($r, 'pembukaan.api') ?? 0)),
                        TextEntry::make('r_abstrak')->label('Berabstrak')->state(fn (LaporanEvaluasi $r): string => $persen($nilai($r, 'kelengkapan.persen_abstrak'))),
                        TextEntry::make('r_naskah')->label('Bernaskah utama')->state(fn (LaporanEvaluasi $r): string => $persen($nilai($r, 'kelengkapan.persen_naskah_utama'))),
                        TextEntry::make('r_teks')->label('Berteks')->state(fn (LaporanEvaluasi $r): string => $persen($nilai($r, 'kelengkapan.persen_teks'))),
                        TextEntry::make('r_tautan')->label('Tautan dapat diakses')->state(fn (LaporanEvaluasi $r): string => $persen($nilai($r, 'kesehatan_tautan.persen_dapat_diakses'))),
                    ]),
                Section::make('Analisis & tindak lanjut')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('analisis')->label('Kendala / temuan')->placeholder('—')->extraAttributes(['style' => 'white-space: pre-line']),
                        TextEntry::make('tindak_lanjut')->label('Rencana tindak lanjut')->placeholder('—')->extraAttributes(['style' => 'white-space: pre-line']),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('periode_mulai', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('pembuat'))
            ->columns([
                TextColumn::make('judul')->label('Judul')->searchable()->wrap(),
                TextColumn::make('periode_mulai')->label('Mulai')->date('d F Y')->sortable(),
                TextColumn::make('periode_selesai')->label('Selesai')->date('d F Y')->sortable(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('pembuat.name')->label('Dibuat oleh')->placeholder('—'),
                TextColumn::make('difinalkan_pada')->label('Difinalkan')->dateTime('d F Y H:i')->placeholder('—'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn (LaporanEvaluasi $record): bool => ! $record->adalahFinal() && (auth()->user()?->can('update', $record) ?? false)),
                self::aksiUnduhPdf(),
            ]);
    }

    public static function aksiHitungRingkasan(): Action
    {
        return Action::make('hitungRingkasan')
            ->label('Hitung ringkasan')
            ->icon(Heroicon::OutlinedCalculator)
            ->color('gray')
            ->visible(fn (LaporanEvaluasi $record): bool => ! $record->adalahFinal() && (auth()->user()?->can('update', $record) ?? false))
            ->requiresConfirmation()
            ->modalDescription('Snapshot angka (koleksi, penambahan, kelengkapan, kesehatan tautan, pemanfaatan, API) dihitung ulang dari data saat ini.')
            ->action(function (LaporanEvaluasi $record): void {
                /** @var User $pengguna */
                $pengguna = auth()->user();
                app(HitungRingkasanLaporan::class)->handle($record, $pengguna);
                $record->refresh();

                Notification::make()->title('Ringkasan dihitung ulang')->success()->send();
            });
    }

    public static function aksiFinalkan(): Action
    {
        return Action::make('finalkan')
            ->label('Finalkan')
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('success')
            ->visible(fn (LaporanEvaluasi $record): bool => ! $record->adalahFinal() && (auth()->user()?->can('finalkan', $record) ?? false))
            ->requiresConfirmation()
            ->modalDescription('Laporan final tidak dapat diubah atau dihitung ulang lagi. PDF selalu dirender dari snapshot ringkasan yang tersimpan.')
            ->action(function (LaporanEvaluasi $record): void {
                /** @var User $pengguna */
                $pengguna = auth()->user();
                app(FinalkanLaporan::class)->handle($record, $pengguna);
                $record->refresh();

                Notification::make()
                    ->title('Laporan difinalkan')
                    ->body('Unduh PDF lalu arsipkan ke Shared Drive "Regulasi FKIP"; bila perlu catat sebagai dokumen jenis LAINNYA.')
                    ->success()
                    ->send();
            });
    }

    public static function aksiUnduhPdf(): Action
    {
        return Action::make('unduhPdf')
            ->label('Unduh PDF')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->visible(fn (LaporanEvaluasi $record): bool => auth()->user()?->can('unduh', $record) ?? false)
            ->url(fn (LaporanEvaluasi $record): string => route('laporan-evaluasi.pdf', $record))
            ->openUrlInNewTab();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLaporanEvaluasi::route('/'),
            'create' => CreateLaporanEvaluasi::route('/create'),
            'view' => ViewLaporanEvaluasi::route('/{record}'),
            'edit' => EditLaporanEvaluasi::route('/{record}/edit'),
        ];
    }
}
