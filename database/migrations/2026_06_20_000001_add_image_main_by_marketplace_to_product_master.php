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

        Schema::table('product_master', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_master', 'image_main_by_marketplace_json')) {
                $table->longText('image_main_by_marketplace_json')->nullable()->after('image12');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_master')) {
            return;
        }

        Schema::table('product_master', function (Blueprint $table): void {
            if (Schema::hasColumn('product_master', 'image_main_by_marketplace_json')) {
                $table->dropColumn('image_main_by_marketplace_json');
            }
        });
    }
};
