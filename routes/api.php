<?php

use App\Http\Controllers\Api\V1\DokumenController;
use App\Http\Controllers\Api\V1\JenisDokumenController;
use App\Http\Controllers\Api\V1\KategoriSistemController;
use App\Http\Controllers\Publik\KesehatanController;
use App\Http\Middleware\PastikanKlienApiAktif;
use Illuminate\Support\Facades\Route;

Route::get('/health', KesehatanController::class)->name('kesehatan');

// API baca JSON:API v1 untuk sistem FKIP lain (02-ARSITEKTUR §8, BR-18): hanya GET.
Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['auth:sanctum', 'abilities:dokumen:baca', PastikanKlienApiAktif::class, 'throttle:api'])
    ->group(function () {
        Route::get('/dokumen', [DokumenController::class, 'index'])->name('dokumen.index');
        Route::get('/dokumen/{dokumen}', [DokumenController::class, 'show'])->whereUuid('dokumen')->name('dokumen.show');
        Route::get('/jenis-dokumen', JenisDokumenController::class)->name('jenis.index');
        Route::get('/kategori-sistem', KategoriSistemController::class)->name('kategori.index');
    });
