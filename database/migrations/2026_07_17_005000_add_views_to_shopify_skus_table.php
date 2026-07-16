<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shopify_skus')) {
            return;
        }
        if (Schema::hasColumn('shopify_skus', 'views')) {
            return;
        }

        Schema::table('shopify_skus', function (Blueprint $table) {
            $table->unsignedInteger('views')->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shopify_skus')) {
            return;
        }
        if (! Schema::hasColumn('shopify_skus', 'views')) {
            return;
        }

        Schema::table('shopify_skus', function (Blueprint $table) {
            $table->dropColumn('views');
        });
    }
};
