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
        Schema::table('amazon_datsheets', function (Blueprint $table) {
            // Add daily units ordered columns (L1 to L30)
            for ($i = 1; $i <= 30; $i++) {
                $table->integer("l{$i}")->default(0)->after('sessions_l90');
            }
            
            // Add daily views/sessions columns (V1 to V30)
            for ($i = 1; $i <= 30; $i++) {
                $table->integer("v{$i}")->default(0)->after('l30');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('amazon_datsheets', function (Blueprint $table) {
            // Drop daily columns
            for ($i = 1; $i <= 30; $i++) {
                $table->dropColumn("l{$i}");
                $table->dropColumn("v{$i}");
            }
        });
    }
};
