<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot dokumen ↔ tag/subjek (03-SKEMA §3.9).
     */
    public function up(): void
    {
        Schema::create('dokumen_tag', function (Blueprint $table) {
            $table->foreignUuid('dokumen_id')->constrained('dokumen')->cascadeOnDelete();
            $table->foreignUuid('tag_id')->index()->constrained('tag')->cascadeOnDelete();

            $table->primary(['dokumen_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumen_tag');
    }
};
