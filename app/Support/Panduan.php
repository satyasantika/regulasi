<?php

namespace App\Support;

use App\Enums\Peran;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * Tautan panduan pengguna HTML statis di public/panduan (F12.6, STANDAR-TEKNIS §2.5). URL lewat asset() agar
 * berawalan subpath /regulasi.
 */
class Panduan
{
    public static function urlIndeks(): string
    {
        return asset('panduan/index.html');
    }

    /**
     * Halaman panduan peran pengguna (peran pertama menurut urutan Peran); indeks bila tanpa peran.
     */
    public static function urlUntuk(?User $pengguna): string
    {
        foreach (Peran::cases() as $peran) {
            if ($pengguna?->hasRole($peran->value)) {
                return asset("panduan/{$peran->value}.html");
            }
        }

        return self::urlIndeks();
    }

    /**
     * Item menu pengguna panel Filament: "Panduan Pengguna" sesuai peran, dibuka di tab baru.
     */
    public static function itemMenuPengguna(): Action
    {
        return Action::make('panduan')
            ->label('Panduan Pengguna')
            ->icon(Heroicon::OutlinedQuestionMarkCircle)
            ->url(function (): string {
                $pengguna = auth()->user();

                return self::urlUntuk($pengguna instanceof User ? $pengguna : null);
            })
            ->openUrlInNewTab();
    }

    /**
     * Tautan di bawah formulir masuk panel.
     *
     * @return array{0: string, 1: \Closure(): HtmlString}
     */
    public static function kaitHalamanMasuk(): array
    {
        return [
            PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
            fn (): HtmlString => new HtmlString(Blade::render(
                '<div class="mt-4 text-center text-sm"><x-filament::link :href="$url" target="_blank" rel="noopener">Panduan Pengguna</x-filament::link></div>',
                ['url' => self::urlIndeks()],
            )),
        ];
    }
}
