<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Teks hasil ekstraksi PDF di balik tautan, 1:1 dengan tautan_berkas (03-SKEMA §3.10a). Tidak ada berkas
     * yang disimpan: PDF hanya singgah di storage/app/tmp selama job berjalan.
     */
    public function up(): void
    {
        Schema::create('ekstraksi_teks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tautan_berkas_id')->unique()->constrained('tautan_berkas')->cascadeOnDelete();
            $table->longText('isi_teks')->nullable();
            $table->unsignedInteger('jumlah_karakter')->nullable();
            $table->unsignedSmallInteger('jumlah_halaman')->nullable();
            $table->unsignedInteger('ukuran_unduhan')->nullable();
            $table->char('hash_sha256', 64)->nullable();
            $table->string('status', 25)->default('belum')->index();
            $table->unsignedTinyInteger('percobaan')->default(0);
            $table->string('pesan', 500)->nullable();
            $table->dateTime('diekstraksi_pada')->nullable();
            $table->dateTime('isi_berubah_pada')->nullable();
            $table->timestamps();

            $table->fullText('isi_teks', 'ft_ekstraksi_isi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ekstraksi_teks');
    }
};
