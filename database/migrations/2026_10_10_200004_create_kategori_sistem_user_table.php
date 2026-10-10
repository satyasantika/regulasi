<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penugasan kontributor ke kategori sistem (03-SKEMA §3.5).
     */
    public function up(): void
    {
        Schema::create('kategori_sistem_user', function (Blueprint $table) {
            $table->foreignUuid('kategori_sistem_id')->constrained('kategori_sistem')->cascadeOnDelete();
            $table->foreignUuid('user_id')->index()->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->primary(['kategori_sistem_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kategori_sistem_user');
    }
};
