<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master kategori sistem FKIP (03-SKEMA §3.4).
     */
    public function up(): void
    {
        Schema::create('kategori_sistem', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 30)->unique();
            $table->string('nama', 100);
            $table->string('slug', 120)->unique();
            $table->text('deskripsi')->nullable();
            $table->string('ikon', 60)->nullable();
            $table->string('warna', 20)->nullable();
            $table->unsignedSmallInteger('urutan')->default(999);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kategori_sistem');
    }
};
