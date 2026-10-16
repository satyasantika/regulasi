<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kamus istilah pencarian (03-SKEMA §3.15a, Profil Ringan): token unik dari judul, singkatan, jenis, T.E.U., dan
     * sinonim. Dibangun ulang oleh `regulasi:bangun-kamus`; dipakai KoreksiIstilah untuk koreksi salah ketik.
     */
    public function up(): void
    {
        Schema::create('istilah_pencarian', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('istilah', 100)->unique();
            $table->string('sumber', 30)->index();
            $table->unsignedInteger('frekuensi')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('istilah_pencarian');
    }
};
