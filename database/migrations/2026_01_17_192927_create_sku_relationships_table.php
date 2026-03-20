<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sku_relationships', function (Blueprint $table) {
            $table->id();
            $table->string('source_sku');
            $table->string('related_sku');
            $table->timestamps();
            
            // Index for faster lookups
            $table->index('source_sku');
            $table->index('related_sku');
            // Prevent duplicate relationships
            $table->unique(['source_sku', 'related_sku']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sku_relationships');
    }
};
