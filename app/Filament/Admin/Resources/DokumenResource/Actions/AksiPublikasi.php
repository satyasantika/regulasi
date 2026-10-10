<?php

namespace App\Filament\Admin\Resources\DokumenResource\Actions;

use App\Actions\Dokumen\AjukanVerifikasi;
use App\Actions\Dokumen\ArsipkanDokumen;
use App\Actions\Dokumen\PeriksaKelengkapan;
use App\Actions\Dokumen\PulihkanDokumen;
use App\Actions\Dokumen\TerbitkanDokumen;
use App\Actions\Dokumen\TolakDokumen;
use App\Enums\StatusPublikasi;
use App\Filament\Admin\Resources\DokumenResource;
use App\Models\Dokumen;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Tombol alur publikasi (F5.2) yang memanggil Action F5.1. Visibilitas = Policy (ajukan/verifikasi/arsipkan)
 * + kesesuaian status (StatusPublikasi::bolehTransisiKe); Action tetap memeriksa ulang di dalam kunci.
 */
class AksiPublikasi
{
    /**
     * @return list<Action>
     */
    public static function semua(): array
    {
        return [self::ajukan(), self::terbitkan(), self::tolak(), self::arsipkan(), self::pulihkan()];
    }

    public static function ajukan(): Action
    {
        return Action::make('ajukanVerifikasi')
            ->label('Ajukan verifikasi')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('primary')
            ->visible(fn (Dokumen $record): bool => self::pengguna()?->can('ajukan', $record) ?? false)
            ->requiresConfirmation()
            ->modalHeading('Ajukan dokumen untuk verifikasi?')
            ->modalDescription('Setelah diajukan, dokumen tidak dapat diubah sampai diverifikasi admin.')
            ->action(fn (Dokumen $record, Component $livewire) => self::jalankan(
                fn (User $u) => app(AjukanVerifikasi::class)->handle($record, $u),
                'Dokumen diajukan untuk verifikasi',
                $record,
                $livewire,
            ));
    }

    public static function terbitkan(): Action
    {
        return Action::make('terbitkan')
            ->label('Terbitkan')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (Dokumen $record): bool => self::boleh('verifikasi', $record, StatusPublikasi::Terbit, [StatusPublikasi::Draf, StatusPublikasi::MenungguVerifikasi]))
            ->modalHeading('Terbitkan dokumen')
            ->modalSubmitActionLabel('Terbitkan')
            ->schema([
                Textarea::make('catatan')->label('Catatan (opsional)')->rows(3)->maxLength(2000),
            ])
            ->action(fn (Dokumen $record, array $data, Component $livewire) => self::jalankan(
                fn (User $u) => app(TerbitkanDokumen::class)->handle($record, $u, filled($data['catatan'] ?? null) ? (string) $data['catatan'] : null),
                'Dokumen diterbitkan',
                $record,
                $livewire,
            ));
    }

    public static function tolak(): Action
    {
        return Action::make('tolak')
            ->label('Tolak')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (Dokumen $record): bool => self::boleh('verifikasi', $record, StatusPublikasi::Ditolak, [StatusPublikasi::MenungguVerifikasi]))
            ->modalHeading('Tolak dokumen')
            ->modalSubmitActionLabel('Tolak')
            ->schema([
                Textarea::make('catatan')
                    ->label('Catatan penolakan (wajib)')
                    ->helperText('Jelaskan apa yang harus diperbaiki kontributor.')
                    ->required()
                    ->minLength(TolakDokumen::MINIMUM_CATATAN)
                    ->maxLength(2000)
                    ->rows(4),
            ])
            ->action(fn (Dokumen $record, array $data, Component $livewire) => self::jalankan(
                fn (User $u) => app(TolakDokumen::class)->handle($record, $u, (string) $data['catatan']),
                'Dokumen ditolak',
                $record,
                $livewire,
            ));
    }

    public static function arsipkan(): Action
    {
        return Action::make('arsipkan')
            ->label('Arsipkan')
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color('gray')
            ->visible(fn (Dokumen $record): bool => self::boleh('arsipkan', $record, StatusPublikasi::Diarsipkan, [StatusPublikasi::Terbit]))
            ->modalHeading('Arsipkan dokumen')
            ->modalDescription('Dokumen tidak lagi tampil di portal, tetapi tidak dihapus (BR-15).')
            ->modalSubmitActionLabel('Arsipkan')
            ->schema([
                Textarea::make('catatan')
                    ->label('Alasan pengarsipan (wajib)')
                    ->required()
                    ->minLength(ArsipkanDokumen::MINIMUM_CATATAN)
                    ->maxLength(2000)
                    ->rows(3),
            ])
            ->action(fn (Dokumen $record, array $data, Component $livewire) => self::jalankan(
                fn (User $u) => app(ArsipkanDokumen::class)->handle($record, $u, (string) $data['catatan']),
                'Dokumen diarsipkan',
                $record,
                $livewire,
            ));
    }

    public static function pulihkan(): Action
    {
        return Action::make('pulihkan')
            ->label('Pulihkan')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('warning')
            ->visible(fn (Dokumen $record): bool => self::boleh('arsipkan', $record, StatusPublikasi::Terbit, [StatusPublikasi::Diarsipkan]))
            ->requiresConfirmation()
            ->modalHeading('Pulihkan dokumen menjadi terbit?')
            ->action(fn (Dokumen $record, Component $livewire) => self::jalankan(
                fn (User $u) => app(PulihkanDokumen::class)->handle($record, $u),
                'Dokumen dipulihkan',
                $record,
                $livewire,
            ));
    }

    /**
     * Terbitkan massal: dokumen yang tidak lengkap/tidak sah dilewati dan dilaporkan.
     */
    public static function terbitkanMassal(): BulkAction
    {
        return BulkAction::make('terbitkanMassal')
            ->label('Terbitkan')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Terbitkan dokumen terpilih?')
            ->modalDescription('Dokumen yang belum memenuhi syarat terbit (BR-05) akan dilewati.')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records): void {
                /** @var User $pengguna */
                $pengguna = auth()->user();
                $berhasil = 0;
                $gagal = [];

                /** @var Dokumen $dokumen */
                foreach ($records as $dokumen) {
                    try {
                        app(TerbitkanDokumen::class)->handle($dokumen, $pengguna);
                        $berhasil++;
                    } catch (ValidationException|AuthorizationException|LockTimeoutException $e) {
                        $gagal[] = $dokumen->sebutan_singkat.': '.self::pesan($e);
                    }
                }

                $notifikasi = Notification::make()
                    ->title("{$berhasil} dokumen diterbitkan, ".count($gagal).' gagal')
                    ->body($gagal === [] ? null : self::baris($gagal));

                ($gagal === [] ? $notifikasi->success() : $notifikasi->warning()->persistent())->send();
            });
    }

    /**
     * @param  'verifikasi'|'arsipkan'  $kemampuan
     * @param  list<StatusPublikasi>  $dari
     */
    private static function boleh(string $kemampuan, Dokumen $dokumen, StatusPublikasi $ke, array $dari): bool
    {
        return in_array($dokumen->status_publikasi, $dari, true)
            && $dokumen->status_publikasi->bolehTransisiKe($ke)
            && (self::pengguna()?->can($kemampuan, $dokumen) ?? false);
    }

    private static function pengguna(): ?User
    {
        $pengguna = auth()->user();

        return $pengguna instanceof User ? $pengguna : null;
    }

    /**
     * Jalankan Action domain; galat validasi/otorisasi/kunci tampil sebagai notifikasi danger.
     *
     * @param  callable(User): Dokumen  $aksi
     */
    private static function jalankan(callable $aksi, string $sukses, Dokumen $dokumen, Component $livewire): void
    {
        $pengguna = self::pengguna();

        if ($pengguna === null) {
            return;
        }

        try {
            $aksi($pengguna);
        } catch (ValidationException|AuthorizationException|LockTimeoutException $e) {
            Notification::make()->title('Gagal')->body(self::baris(explode("\n", self::pesan($e))))->danger()->persistent()->send();

            return;
        }

        Notification::make()->title($sukses)->success()->send();

        $dokumen->refresh();

        // Halaman ubah: bila pengguna tidak lagi boleh mengubah (mis. kontributor setelah mengajukan), pindah ke halaman lihat.
        if ($livewire instanceof EditRecord && ! $pengguna->can('update', $dokumen)) {
            $livewire->redirect(DokumenResource::getUrl('view', ['record' => $dokumen]));
        }
    }

    private static function pesan(ValidationException|AuthorizationException|LockTimeoutException $e): string
    {
        return match (true) {
            $e instanceof ValidationException => implode("\n", Arr::flatten($e->errors())),
            $e instanceof LockTimeoutException => 'Dokumen sedang diproses pengguna lain. Coba lagi sebentar lagi.',
            default => 'Anda tidak berwenang melakukan tindakan ini.',
        };
    }

    /**
     * Baris pesan (di-escape) dipisah <br> untuk badan notifikasi.
     *
     * @param  list<string>  $baris
     */
    private static function baris(array $baris): HtmlString
    {
        return new HtmlString(implode('<br>', array_map(fn (string $b): string => e($b), $baris)));
    }

    /**
     * Ringkasan kelengkapan "x/y" (butir BR-05 yang terpenuhi).
     */
    public static function ringkasanKelengkapan(Dokumen $dokumen): string
    {
        $daftar = app(PeriksaKelengkapan::class)->daftar($dokumen);
        $terpenuhi = count(array_filter($daftar, fn (array $butir): bool => $butir['terpenuhi']));

        return $terpenuhi.'/'.count($daftar);
    }
}
