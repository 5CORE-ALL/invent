<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Instructions item PKG — per product_master row (Dim Wt Master).
     */
    public function up(): void
    {
        if (Schema::hasTable('instructions_item_pkg')) {
            return;
        }

        Schema::create('instructions_item_pkg', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_master_id')->unique()->constrained('product_master')->cascadeOnDelete();
            $table->text('instructions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructions_item_pkg');
    }
};
