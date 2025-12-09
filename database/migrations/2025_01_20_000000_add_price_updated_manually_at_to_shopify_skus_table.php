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
        Schema::table('shopify_skus', function (Blueprint $table) {
            $table->timestamp('price_updated_manually_at')->nullable()->after('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shopify_skus', function (Blueprint $table) {
            $table->dropColumn('price_updated_manually_at');
        });
    }
};

