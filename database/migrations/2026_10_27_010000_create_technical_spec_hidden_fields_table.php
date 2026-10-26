<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('technical_spec_hidden_fields')) {
            return;
        }

        Schema::create('technical_spec_hidden_fields', function (Blueprint $table) {
            $table->id();
            // eBay category the field was crossed out for ("ebay:<id>", or the category path / SKU when there is no id).
            $table->string('scope', 191);
            // Normalized field name (lower case, letters and digits only; synonyms share one key).
            $table->string('field_key', 191);
            $table->string('label', 255)->nullable();
            $table->unsignedBigInteger('hidden_by')->nullable();
            $table->timestamps();

            $table->unique(['scope', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_spec_hidden_fields');
    }
};
