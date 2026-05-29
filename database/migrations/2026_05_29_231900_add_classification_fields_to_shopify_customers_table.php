<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shopify_customers')) {
            return;
        }

        Schema::table('shopify_customers', function (Blueprint $table) {
            if (! Schema::hasColumn('shopify_customers', 'customer_type')) {
                $table->string('customer_type', 32)->nullable()->index()->after('raw_payload');
            }
            if (! Schema::hasColumn('shopify_customers', 'marketplace_channel')) {
                $table->string('marketplace_channel', 64)->nullable()->index()->after('customer_type');
            }
            if (! Schema::hasColumn('shopify_customers', 'classification_source')) {
                $table->string('classification_source', 32)->nullable()->index()->after('marketplace_channel');
            }
            if (! Schema::hasColumn('shopify_customers', 'classification_reason')) {
                $table->string('classification_reason')->nullable()->after('classification_source');
            }
            if (! Schema::hasColumn('shopify_customers', 'classification_overridden')) {
                $table->boolean('classification_overridden')->default(false)->index()->after('classification_reason');
            }
            if (! Schema::hasColumn('shopify_customers', 'classified_at')) {
                $table->timestamp('classified_at')->nullable()->index()->after('classification_overridden');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shopify_customers')) {
            return;
        }

        Schema::table('shopify_customers', function (Blueprint $table) {
            foreach ([
                'classified_at',
                'classification_overridden',
                'classification_reason',
                'classification_source',
                'marketplace_channel',
                'customer_type',
            ] as $column) {
                if (Schema::hasColumn('shopify_customers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
