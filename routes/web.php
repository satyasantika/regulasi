<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\ImpersonasiController;
use App\Http\Controllers\Laporan\UnduhEksporController;
use App\Http\Controllers\Laporan\UnduhLaporanEvaluasiController;
use App\Http\Controllers\Publik\BukaTautanController;
use App\Http\Controllers\Publik\DetailDokumenController;
use App\Http\Controllers\Publik\SitemapController;
use App\Livewire\Publik\Beranda;
use App\Livewire\Publik\DaftarDokumen;
use Illuminate\Support\Facades\Route;

// Portal publik (02-ARSITEKTUR §8).
Route::get('/', Beranda::class)->name('beranda');
Route::get('/dokumen', DaftarDokumen::class)->name('dokumen.index');
Route::get('/jenis/{jenisDokumen:slug}', DaftarDokumen::class)->name('dokumen.jenis');
Route::get('/kategori/{kategoriSistem:slug}', DaftarDokumen::class)->name('dokumen.kategori');
Route::get('/dokumen/{dokumen}/{slug?}', [DetailDokumenController::class, 'show'])
    ->whereUuid('dokumen')
    ->name('dokumen.show');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// Buka tautan berkas berotorisasi (BR-17): Policy → log → redirect ke URL tersimpan.
Route::get('/d/{tautanBerkas}/buka', BukaTautanController::class)
    ->whereUuid('tautanBerkas')
    ->middleware('throttle:buka')
    ->name('tautan.buka');

// Buka tautan dokumen internal dari API (BR-02, BR-17): URL bertanda tangan 10 menit, saluran api.
Route::get('/d/{tautanBerkas}/buka-api', [BukaTautanController::class, 'api'])
    ->whereUuid('tautanBerkas')
    ->middleware(['signed', 'throttle:buka'])
    ->name('tautan.buka-api');

// Unduh ekspor Excel sementara (F11.2): bertanda tangan, hanya peminta; berkas dihapus ≤ 24 jam.
Route::get('/ekspor/{berkas}', UnduhEksporController::class)
    ->middleware(['auth', 'signed'])
    ->name('ekspor.unduh');

// PDF laporan evaluasi periode (F11.4): di-stream dari snapshot, tidak disimpan.
Route::get('/laporan-evaluasi/{laporanEvaluasi}/pdf', UnduhLaporanEvaluasiController::class)
    ->whereUuid('laporanEvaluasi')
    ->middleware('auth')
    ->name('laporan-evaluasi.pdf');

// Akhiri impersonasi super-admin (dimulai dari aksi tabel Pengguna; tercatat di log aktivitas).
Route::post('/impersonasi/berhenti', [ImpersonasiController::class, 'berhenti'])
    ->middleware('auth')
    ->name('impersonasi.berhenti');

// Masuk dengan Google (aktif bila GOOGLE_LOGIN_ENABLED=true; selain itu 404).
Route::prefix('auth/google')->middleware('throttle:google-login')->group(function () {
    Route::get('redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});
