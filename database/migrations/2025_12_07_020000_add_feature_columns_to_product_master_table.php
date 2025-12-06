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
        Schema::table('product_master', function (Blueprint $table) {
            $table->string('feature1', 100)->nullable()->after('product_description');
            $table->string('feature2', 100)->nullable()->after('feature1');
            $table->string('feature3', 100)->nullable()->after('feature2');
            $table->string('feature4', 100)->nullable()->after('feature3');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_master', function (Blueprint $table) {
            $table->dropColumn(['feature1', 'feature2', 'feature3', 'feature4']);
        });
    }
};
