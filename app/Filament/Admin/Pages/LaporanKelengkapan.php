<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Exports\KelengkapanDokumenExporter;
use App\Filament\Admin\Resources\DokumenResource;
use App\Models\Dokumen;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use App\Models\User;
use App\Support\Laporan\KriteriaKelengkapan;
use App\Support\Laporan\LingkupLaporan;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Laporan kelengkapan metadata dokumen terbit (F11.2, PRD §8, US-LAP-02): tanda ✓/✗ per kriteria, skor (%),
 * ringkasan persentase per kategori, ekspor Excel (Filament ExportAction, antrean `ekspor`).
 */
class LaporanKelengkapan extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'laporan/kelengkapan';

    protected static ?string $title = 'Kelengkapan Metadata';

    protected static ?string $navigationLabel = 'Kelengkapan Metadata';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('laporan.lihat') ?? false;
    }

    public function content(Schema $schema): Schema
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();
        $ringkasan = KriteriaKelengkapan::ringkasanPerKategori(LingkupLaporan::untuk($pengguna));

        return $schema->components([
            Section::make('Rata-rata skor kelengkapan per kategori')
                ->description('Dokumen terbit; skor = kriteria terpenuhi dari '.count(KriteriaKelengkapan::LABEL).' kriteria.')
                ->collapsible()
                ->columns(['default' => 2, 'md' => 4, 'xl' => 6])
                ->schema(array_map(
                    fn (array $baris): TextEntry => TextEntry::make('kategori_'.$baris['kode'])
                        ->label($baris['nama'])
                        ->state($baris['jumlah'] === 0 ? '—' : $baris['skor'].'%')
                        ->helperText($baris['jumlah'].' dokumen · '.$baris['lengkap'].' lengkap'),
                    $ringkasan,
                )),
            EmbeddedTable::make(),
        ]);
    }

    /**
     * @return Builder<Dokumen>
     */
    public static function query(User $pengguna): Builder
    {
        $query = LingkupLaporan::untuk($pengguna)->terapkan(Dokumen::query()->terbit())
            ->with(['jenisDokumen', 'kategoriSistem']);

        return KriteriaKelengkapan::pilihKolom($query);
    }

    public function table(Table $table): Table
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();
        $lingkup = LingkupLaporan::untuk($pengguna);

        $kolomKriteria = array_map(
            fn (string $kunci, string $label): IconColumn => IconColumn::make('lengkap_'.$kunci)
                ->label($label)
                ->boolean()
                ->alignCenter(),
            array_keys(KriteriaKelengkapan::LABEL),
            KriteriaKelengkapan::LABEL,
        );

        return $table
            ->query(fn (): Builder => self::query($pengguna))
            ->defaultSort('skor_kelengkapan')
            ->emptyStateHeading('Tidak ada dokumen terbit')
            ->columns([
                TextColumn::make('sebutan_singkat')->label('Sebutan')->searchable(),
                TextColumn::make('judul')->label('Judul')->limit(50)->wrap()->searchable()->toggleable(),
                TextColumn::make('jenisDokumen.nama')->label('Jenis')->toggleable(),
                ...$kolomKriteria,
                TextColumn::make('skor_kelengkapan')
                    ->label('Skor')
                    ->suffix('%')
                    ->sortable()
                    ->badge()
                    ->color(fn (mixed $state): string => (int) $state >= 100 ? 'success' : ((int) $state >= 60 ? 'warning' : 'danger')),
            ])
            ->filters([
                Filter::make('belum_lengkap')
                    ->label('Hanya yang belum lengkap')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereRaw('NOT '.KriteriaKelengkapan::sqlLengkap())),
                SelectFilter::make('kategori')
                    ->label('Kategori')
                    ->options(fn (): array => KategoriSistem::query()
                        ->when($lingkup->kategoriIds !== null, fn (Builder $q) => $q->whereKey($lingkup->kategoriIds ?? []))
                        ->terurut()
                        ->pluck('nama', 'id')
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('kategoriSistem', fn (Builder $q) => $q->whereKey($data['value']))
                        : $query),
                SelectFilter::make('jenis')
                    ->label('Jenis')
                    ->options(fn (): array => JenisDokumen::query()->terurut()->pluck('nama', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->where('dokumen.jenis_dokumen_id', $data['value'])
                        : $query),
            ])
            ->headerActions([
                ExportAction::make('eksporExcel')
                    ->label('Ekspor Excel')
                    ->exporter(KelengkapanDokumenExporter::class)
                    ->formats([ExportFormat::Xlsx])
                    ->columnMappingColumns(2)
                    ->visible(fn (): bool => $pengguna->can('laporan.ekspor'))
                    ->authorize(fn (): bool => $pengguna->can('laporan.ekspor')),
            ])
            ->recordActions([
                Action::make('buka')
                    ->label('Buka')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (Dokumen $record): string => $pengguna->can('update', $record)
                        ? DokumenResource::getUrl('edit', ['record' => $record])
                        : DokumenResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
