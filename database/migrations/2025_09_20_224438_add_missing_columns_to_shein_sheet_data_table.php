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
        Schema::table('shein_sheet_data', function (Blueprint $table) {
            $table->string('sku')->unique();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('roi', 10, 2)->nullable();
            $table->integer('l30')->nullable();
            $table->string('buy_link')->nullable();
            $table->string('s_link')->nullable();
            $table->integer('views_clicks')->nullable();
            $table->decimal('lmp', 10, 2)->nullable();
            $table->string('link1')->nullable();
            $table->string('link2')->nullable();
            $table->string('link3')->nullable();
            $table->string('link4')->nullable();
            $table->string('link5')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shein_sheet_data', function (Blueprint $table) {
            $table->dropColumn(['sku', 'price', 'roi', 'l30', 'buy_link', 's_link', 'views_clicks', 'lmp', 'link1', 'link2', 'link3', 'link4', 'link5']);
        });
    }
};
