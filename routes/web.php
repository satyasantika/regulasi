<?php

use App\Http\Controllers\Publik\BukaTautanController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Buka tautan berkas berotorisasi (BR-17): Policy → log → redirect ke URL tersimpan.
Route::get('/d/{tautanBerkas}/buka', BukaTautanController::class)
    ->whereUuid('tautanBerkas')
    ->middleware('throttle:buka')
    ->name('tautan.buka');
