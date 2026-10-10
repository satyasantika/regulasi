<?php

namespace App\Filament\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Pages\Login;
use Filament\Notifications\Notification;

/**
 * Halaman masuk kedua panel: dibatasi 5 percobaan/menit per kombinasi surel + IP.
 */
class Masuk extends Login
{
    /**
     * @param  string|null  $method
     * @param  string|null  $component
     */
    protected function getRateLimitKey($method, $component = null): string
    {
        return self::kunciPembatas((string) ($this->data['email'] ?? ''), (string) request()->ip());
    }

    public static function kunciPembatas(string $email, string $ip): string
    {
        return 'masuk-panel:'.hash('sha256', mb_strtolower(trim($email)).'|'.$ip);
    }

    protected function getRateLimitedNotification(TooManyRequestsException $exception): ?Notification
    {
        return Notification::make()
            ->title('Terlalu banyak percobaan masuk')
            ->body("Coba lagi dalam {$exception->secondsUntilAvailable} detik.")
            ->danger();
    }
}
