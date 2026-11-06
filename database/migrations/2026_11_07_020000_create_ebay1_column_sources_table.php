<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ebay1 Data: a column of a category tab that always reads its values from a Technical Specifications column.
     */
    public function up(): void
    {
        if (Schema::hasTable('ebay1_column_sources')) {
            return;
        }

        Schema::create('ebay1_column_sources', function (Blueprint $table) {
            $table->id();
            $table->string('category', 500)->default('');
            $table->char('category_hash', 40);
            $table->string('column_name', 191);
            $table->string('column_key', 191);
            $table->string('source_name', 191);
            $table->string('source_key', 191);
            $table->unsignedBigInteger('saved_by')->nullable();
            $table->timestamps();
            $table->unique(['category_hash', 'column_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebay1_column_sources');
    }
};
