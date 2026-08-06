<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('po_custom_term_options')) {
            Schema::create('po_custom_term_options', function (Blueprint $table) {
                $table->id();
                // utf8mb4 unique index max is 768 chars (3072 bytes).
                $table->string('value', 768);
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
                $table->unique('value');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('po_custom_term_options');
    }
};
