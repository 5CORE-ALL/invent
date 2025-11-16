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
        Schema::table('wayfair_products', function (Blueprint $table) {
            $table->string('sku')->nullable()->change();
            $table->string('po_number')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wayfair_products', function (Blueprint $table) {
            $table->string('sku')->nullable(false)->change();
            $table->string('po_number')->nullable(false)->change();
        });
    }
};
