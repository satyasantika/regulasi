<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master jenis dokumen (03-SKEMA §3.2).
     */
    public function up(): void
    {
        Schema::create('jenis_dokumen', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 30)->unique();
            $table->string('nama', 150);
            $table->string('singkatan', 50)->nullable();
            $table->string('slug', 160)->unique();
            $table->string('kelompok', 30)->index();
            $table->unsignedSmallInteger('urutan')->default(999)->index();
            $table->boolean('wajib_nomor')->default(true);
            $table->text('deskripsi')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jenis_dokumen');
    }
};
