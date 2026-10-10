<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indeks daftar publik terbaru (F12.2, docs/KINERJA.md): urutan bawaan portal, cadangan pencarian FULLTEXT,
     * beranda, dan sitemap adalah dokumen terbit (+ visibilitas) terurut dipublikasikan_pada DESC. Dengan indeks
     * komposit ini LIMIT 20 dibaca langsung dari indeks tanpa filesort.
     */
    public function up(): void
    {
        Schema::table('dokumen', function (Blueprint $table) {
            $table->index(['status_publikasi', 'visibilitas', 'dipublikasikan_pada'], 'idx_dokumen_publik_terbaru');
        });
    }

    public function down(): void
    {
        Schema::table('dokumen', function (Blueprint $table) {
            $table->dropIndex('idx_dokumen_publik_terbaru');
        });
    }
};
