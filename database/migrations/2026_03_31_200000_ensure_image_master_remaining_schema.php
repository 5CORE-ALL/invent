<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('macy_metrics')) {
            Schema::create('macy_metrics', function (Blueprint $table) {
                $table->id();
                $table->string('sku')->nullable()->index();
                $table->longText('image_urls')->nullable();
                $table->longText('image_master_json')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('macy_metrics', function (Blueprint $table) {
                if (! Schema::hasColumn('macy_metrics', 'image_urls')) {
                    $table->longText('image_urls')->nullable();
                }
                if (! Schema::hasColumn('macy_metrics', 'image_master_json')) {
                    $table->longText('image_master_json')->nullable();
                }
            });
        }

        if (Schema::hasTable('ebay_metrics') && ! Schema::hasColumn('ebay_metrics', 'image_urls')) {
            Schema::table('ebay_metrics', function (Blueprint $table) {
                $table->longText('image_urls')->nullable();
            });
        }

        if (Schema::hasTable('reverb_products') && ! Schema::hasColumn('reverb_products', 'image_master_json')) {
            Schema::table('reverb_products', function (Blueprint $table) {
                $table->longText('image_master_json')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('macy_metrics')) {
            Schema::table('macy_metrics', function (Blueprint $table) {
                if (Schema::hasColumn('macy_metrics', 'image_master_json')) {
                    $table->dropColumn('image_master_json');
                }
                if (Schema::hasColumn('macy_metrics', 'image_urls')) {
                    $table->dropColumn('image_urls');
                }
            });
        }

        if (Schema::hasTable('ebay_metrics') && Schema::hasColumn('ebay_metrics', 'image_urls')) {
            Schema::table('ebay_metrics', function (Blueprint $table) {
                $table->dropColumn('image_urls');
            });
        }

        if (Schema::hasTable('reverb_products') && Schema::hasColumn('reverb_products', 'image_master_json')) {
            Schema::table('reverb_products', function (Blueprint $table) {
                $table->dropColumn('image_master_json');
            });
        }
    }
};
