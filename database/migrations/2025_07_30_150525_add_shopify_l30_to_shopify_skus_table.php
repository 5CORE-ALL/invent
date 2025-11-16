<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shopify_skus', function (Blueprint $table) {
            $table->integer('shopify_l30')->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('shopify_skus', function (Blueprint $table) {
            $table->dropColumn('shopify_l30');
        });
    }
};

