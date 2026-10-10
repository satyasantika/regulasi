<?php

use App\Http\Controllers\Publik\BukaTautanController;
use App\Livewire\Publik\Beranda;
use App\Livewire\Publik\DaftarDokumen;
use Illuminate\Support\Facades\Route;

// Portal publik (02-ARSITEKTUR §8).
Route::get('/', Beranda::class)->name('beranda');
Route::get('/dokumen', DaftarDokumen::class)->name('dokumen.index');
Route::get('/jenis/{jenisDokumen:slug}', DaftarDokumen::class)->name('dokumen.jenis');
Route::get('/kategori/{kategoriSistem:slug}', DaftarDokumen::class)->name('dokumen.kategori');

// Buka tautan berkas berotorisasi (BR-17): Policy → log → redirect ke URL tersimpan.
Route::get('/d/{tautanBerkas}/buka', BukaTautanController::class)
    ->whereUuid('tautanBerkas')
    ->middleware('throttle:buka')
    ->name('tautan.buka');
