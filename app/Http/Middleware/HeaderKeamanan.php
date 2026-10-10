<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan dasar (02-ARSITEKTUR §8; pengerasan lengkap di F12.1). CSP kompatibel Filament/Livewire/Alpine
 * (skrip inline & evaluasi Alpine); sematan hanya dari Google Drive/Docs (pratinjau naskah, F8.3).
 */
class HeaderKeamanan
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        if (! $response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', self::csp());
        }

        return $response;
    }

    public static function csp(): string
    {
        $vite = Vite::isRunningHot() ? ' '.trim((string) file_get_contents(public_path('hot'))) : '';
        $viteWs = $vite === '' ? '' : ' '.preg_replace('#^http#', 'ws', trim($vite));

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'".$vite,
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net".$vite,
            "font-src 'self' data: https://fonts.bunny.net",
            "img-src 'self' data: blob: https://ui-avatars.com",
            'frame-src https://drive.google.com https://docs.google.com',
            "connect-src 'self'".$vite.$viteWs,
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }
}
