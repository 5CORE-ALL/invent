<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_master')) {
            return;
        }
        Schema::table('product_master', function (Blueprint $table) {
            if (! Schema::hasColumn('product_master', 'description_v2_bullets')) {
                $table->longText('description_v2_bullets')->nullable()->after('description_600');
            }
            if (! Schema::hasColumn('product_master', 'description_v2_description')) {
                $table->longText('description_v2_description')->nullable();
            }
            if (! Schema::hasColumn('product_master', 'description_v2_images')) {
                $table->json('description_v2_images')->nullable();
            }
            if (! Schema::hasColumn('product_master', 'description_v2_features')) {
                $table->json('description_v2_features')->nullable();
            }
            if (! Schema::hasColumn('product_master', 'description_v2_specifications')) {
                $table->json('description_v2_specifications')->nullable();
            }
            if (! Schema::hasColumn('product_master', 'description_v2_package')) {
                $table->longText('description_v2_package')->nullable();
            }
            if (! Schema::hasColumn('product_master', 'description_v2_brand')) {
                $table->longText('description_v2_brand')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_master')) {
            return;
        }
        Schema::table('product_master', function (Blueprint $table) {
            foreach ([
                'description_v2_bullets',
                'description_v2_description',
                'description_v2_images',
                'description_v2_features',
                'description_v2_specifications',
                'description_v2_package',
                'description_v2_brand',
            ] as $col) {
                if (Schema::hasColumn('product_master', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
