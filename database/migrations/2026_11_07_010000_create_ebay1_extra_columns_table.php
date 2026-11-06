<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columns added by hand to a category tab of Ebay1 Data (they also exist as blank specifications
     * of the category's SKUs on Technical Specifications).
     */
    public function up(): void
    {
        if (Schema::hasTable('ebay1_extra_columns')) {
            return;
        }

        Schema::create('ebay1_extra_columns', function (Blueprint $table) {
            $table->id();
            $table->string('category', 500)->default('');
            $table->char('category_hash', 40);
            $table->string('name', 191);
            $table->string('name_key', 191);
            $table->unsignedBigInteger('added_by')->nullable();
            $table->timestamps();
            $table->unique(['category_hash', 'name_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebay1_extra_columns');
    }
};
