<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tautan berkas polimorfik (03-SKEMA §3.10, STANDAR-TEKNIS §1a). Tidak ada kolom path/disk.
     */
    public function up(): void
    {
        Schema::create('tautan_berkas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('pemilik_type', 255);
            $table->uuid('pemilik_id');
            $table->string('jenis', 30)->default('lampiran');
            $table->string('label', 255)->nullable();
            $table->string('url', 2048);
            $table->string('penyedia', 20)->index();
            $table->string('drive_file_id', 100)->nullable()->index();
            $table->string('status_cek', 25)->default('belum')->index();
            $table->dateTime('dicek_pada')->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->unsignedInteger('jumlah_dibuka')->default(0);
            $table->foreignUuid('ditambahkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pemilik_type', 'pemilik_id', 'jenis', 'urutan'], 'idx_tautan_pemilik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tautan_berkas');
    }
};
