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
            if (! Schema::hasColumn('shopify_customers', 'website')) {
                $table->string('website', 255)->nullable();
            }
            if (! Schema::hasColumn('shopify_customers', 'facebook')) {
                $table->string('facebook', 255)->nullable();
            }
            if (! Schema::hasColumn('shopify_customers', 'instagram')) {
                $table->string('instagram', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shopify_customers')) {
            return;
        }

        Schema::table('shopify_customers', function (Blueprint $table) {
            foreach (['website', 'facebook', 'instagram'] as $column) {
                if (Schema::hasColumn('shopify_customers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
