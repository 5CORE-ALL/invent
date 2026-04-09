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
        if (! Schema::hasTable('shopify_skus') || Schema::hasColumn('shopify_skus', 'variant_id')) {
            return;
        }

        $afterId = Schema::hasColumn('shopify_skus', 'id');

        Schema::table('shopify_skus', function (Blueprint $table) use ($afterId): void {
            if ($afterId) {
                $table->string('variant_id')->nullable()->after('id');
            } else {
                $table->string('variant_id')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('shopify_skus') || ! Schema::hasColumn('shopify_skus', 'variant_id')) {
            return;
        }

        Schema::table('shopify_skus', function (Blueprint $table) {
            $table->dropColumn('variant_id');
        });
    }
};
