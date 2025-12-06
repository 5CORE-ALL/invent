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
            $table->text('bullet1')->nullable()->after('title60');
            $table->text('bullet2')->nullable()->after('bullet1');
            $table->text('bullet3')->nullable()->after('bullet2');
            $table->text('bullet4')->nullable()->after('bullet3');
            $table->text('bullet5')->nullable()->after('bullet4');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_master', function (Blueprint $table) {
            $table->dropColumn(['bullet1', 'bullet2', 'bullet3', 'bullet4', 'bullet5']);
        });
    }
};
