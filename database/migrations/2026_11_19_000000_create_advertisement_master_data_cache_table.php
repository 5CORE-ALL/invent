<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-built /advertisement-master grid payload. Building it runs hundreds of
 * queries, so a scheduled job (8:00 AM Pacific) builds it once and stores the
 * JSON here; the page then just reads this row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertisement_master_data_cache', function (Blueprint $table) {
            $table->id();
            $table->string('cache_key', 64)->unique();
            $table->longText('payload');
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertisement_master_data_cache');
    }
};
