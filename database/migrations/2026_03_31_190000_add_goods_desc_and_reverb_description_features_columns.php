<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('temu_metrics') && ! Schema::hasColumn('temu_metrics', 'goods_desc')) {
            Schema::table('temu_metrics', function (Blueprint $table) {
                $table->text('goods_desc')->nullable()->after('goods_summary');
            });
        }

        if (Schema::hasTable('reverb_products')) {
            Schema::table('reverb_products', function (Blueprint $table) {
                if (! Schema::hasColumn('reverb_products', 'description')) {
                    $table->longText('description')->nullable();
                }
                if (! Schema::hasColumn('reverb_products', 'features')) {
                    $table->json('features')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('temu_metrics') && Schema::hasColumn('temu_metrics', 'goods_desc')) {
            Schema::table('temu_metrics', function (Blueprint $table) {
                $table->dropColumn('goods_desc');
            });
        }

        if (Schema::hasTable('reverb_products')) {
            Schema::table('reverb_products', function (Blueprint $table) {
                if (Schema::hasColumn('reverb_products', 'features')) {
                    $table->dropColumn('features');
                }
                if (Schema::hasColumn('reverb_products', 'description')) {
                    $table->dropColumn('description');
                }
            });
        }
    }
};
