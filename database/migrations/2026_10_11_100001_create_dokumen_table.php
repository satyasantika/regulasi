<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dokumen hukum dan pendukung (03-SKEMA §3.7).
     */
    public function up(): void
    {
        Schema::create('dokumen', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 255)->unique();
            $table->foreignUuid('jenis_dokumen_id')->constrained('jenis_dokumen')->restrictOnDelete();
            $table->foreignUuid('badan_penetap_id')->constrained('badan_penetap')->restrictOnDelete();
            $table->string('nomor', 100)->nullable()->index();
            $table->unsignedSmallInteger('tahun')->index();
            $table->string('judul', 1000);
            $table->string('sebutan', 500);
            $table->string('sebutan_singkat', 150)->index();
            $table->string('tempat_penetapan', 100)->nullable();
            $table->date('tanggal_penetapan')->nullable()->index();
            $table->date('tanggal_pengundangan')->nullable();
            $table->date('tanggal_berlaku')->nullable();
            $table->string('sumber', 255)->nullable();
            $table->char('bahasa', 2)->default('id');
            $table->text('abstrak')->nullable();
            $table->text('catatan')->nullable();
            $table->string('status_berlaku', 30)->default('berlaku')->index();
            $table->boolean('status_berlaku_manual')->default(false);
            $table->string('status_publikasi', 30)->default('draf');
            $table->string('visibilitas', 20)->default('publik');
            $table->boolean('tanpa_berkas')->default(false);
            $table->unsignedInteger('jumlah_dibuka')->default(0);
            $table->text('catatan_verifikasi')->nullable();
            $table->foreignUuid('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('diverifikasi_pada')->nullable();
            $table->dateTime('dipublikasikan_pada')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            // BR-06: baris dengan nomor NULL tidak saling bertabrakan; baris soft-deleted tetap memblokir.
            $table->unique(['jenis_dokumen_id', 'badan_penetap_id', 'nomor', 'tahun'], 'uk_dokumen_identitas');
            $table->index(['status_publikasi', 'visibilitas', 'tahun'], 'idx_dokumen_publik');
            $table->index(['jenis_dokumen_id', 'tahun'], 'idx_dokumen_jenis_tahun');
            $table->fullText(['judul', 'sebutan_singkat', 'abstrak'], 'ft_dokumen');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumen');
    }
};
