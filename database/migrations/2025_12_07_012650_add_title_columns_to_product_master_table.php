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
            $table->text('title150')->nullable()->after('sku');
            $table->text('title100')->nullable()->after('title150');
            $table->text('title80')->nullable()->after('title100');
            $table->text('title60')->nullable()->after('title80');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_master', function (Blueprint $table) {
            $table->dropColumn(['title150', 'title100', 'title80', 'title60']);
        });
    }
};
