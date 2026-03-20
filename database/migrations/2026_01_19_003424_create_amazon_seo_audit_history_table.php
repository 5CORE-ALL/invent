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
        Schema::create('amazon_seo_audit_history', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 100)->index()->comment('Amazon SKU');
            $table->text('checklist_text')->comment('Checklist entry text (max 180 chars)');
            $table->unsignedBigInteger('user_id')->nullable()->comment('User who created the entry');
            $table->timestamps();
            
            // Add index for faster lookups
            $table->index(['sku', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amazon_seo_audit_history');
    }
};
