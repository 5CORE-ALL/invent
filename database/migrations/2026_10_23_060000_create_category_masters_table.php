<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('category_masters')) {
            return;
        }

        Schema::create('category_masters', function (Blueprint $table) {
            $table->id();
            // CP Master SKU (parent rows use their "PARENT ..." SKU).
            $table->string('sku', 191)->unique();
            $table->string('parent', 191)->nullable()->index();
            $table->string('category_ebay', 255)->nullable();
            $table->string('category_amz', 255)->nullable();
            $table->string('category_temu', 255)->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_masters');
    }
};
