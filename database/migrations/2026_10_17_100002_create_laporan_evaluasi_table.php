<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laporan evaluasi periode (03-SKEMA §3.15, US-LAP-03). PDF tidak disimpan; `ringkasan` = snapshot angka.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_evaluasi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('judul', 255);
            $table->date('periode_mulai');
            $table->date('periode_selesai');
            $table->json('ringkasan');
            $table->text('analisis')->nullable();
            $table->text('tindak_lanjut')->nullable();
            $table->string('status', 10)->default('draf');
            $table->foreignUuid('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('difinalkan_pada')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_evaluasi');
    }
};
