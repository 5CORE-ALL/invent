<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Technical Specifications: a column that always takes its value from another column of the same SKU.
     */
    public function up(): void
    {
        if (Schema::hasTable('technical_spec_column_sources')) {
            return;
        }

        Schema::create('technical_spec_column_sources', function (Blueprint $table) {
            $table->id();
            $table->string('column_name', 191);
            $table->string('column_key', 191)->unique();
            $table->string('source_name', 191);
            $table->string('source_key', 191);
            $table->unsignedBigInteger('saved_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_spec_column_sources');
    }
};
