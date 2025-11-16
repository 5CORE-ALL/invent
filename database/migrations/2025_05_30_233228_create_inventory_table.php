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
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->integer('verified_stock')->nullable();
            $table->integer('to_adjust')->nullable();
            $table->string('reason')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->string('approved_by')->nullable();
            $table->timestamps(); // includes created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory');
    }
};
