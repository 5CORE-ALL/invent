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
        Schema::table('ebay_3_metrics', function (Blueprint $table) {
            $table->text('lmp_link')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ebay_3_metrics', function (Blueprint $table) {
            $table->string('lmp_link', 500)->nullable()->change();
        });
    }
};
