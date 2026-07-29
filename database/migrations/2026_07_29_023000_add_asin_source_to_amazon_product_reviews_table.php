<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('amazon_product_reviews')) {
            return;
        }

        Schema::table('amazon_product_reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('amazon_product_reviews', 'asin')) {
                $table->string('asin', 32)->nullable()->after('sku')->index();
            }
            if (! Schema::hasColumn('amazon_product_reviews', 'source')) {
                $table->string('source', 40)->nullable()->after('review_count');
            }
            if (! Schema::hasColumn('amazon_product_reviews', 'fetched_at')) {
                $table->timestamp('fetched_at')->nullable()->after('updated_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('amazon_product_reviews')) {
            return;
        }

        Schema::table('amazon_product_reviews', function (Blueprint $table) {
            if (Schema::hasColumn('amazon_product_reviews', 'fetched_at')) {
                $table->dropColumn('fetched_at');
            }
            if (Schema::hasColumn('amazon_product_reviews', 'source')) {
                $table->dropColumn('source');
            }
            if (Schema::hasColumn('amazon_product_reviews', 'asin')) {
                $table->dropColumn('asin');
            }
        });
    }
};
