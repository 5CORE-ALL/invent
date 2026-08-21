<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_raw_images')) {
            return;
        }

        Schema::table('product_raw_images', function (Blueprint $table) {
            if (! Schema::hasColumn('product_raw_images', 'kind')) {
                $table->string('kind', 32)->default('raw')->after('sku')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_raw_images') || ! Schema::hasColumn('product_raw_images', 'kind')) {
            return;
        }

        Schema::table('product_raw_images', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropColumn('kind');
        });
    }
};
