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
        Schema::table('fba_orders', function (Blueprint $table) {
            $table->string('status')->nullable();
            $table->string('seller_sku')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fba_orders', function (Blueprint $table) {
            $table->dropColumn(['status', 'seller_sku']);
        });
    }
};
