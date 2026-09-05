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
            if (! Schema::hasColumn('shopify_customers', 'whatsapp_available')) {
                $table->boolean('whatsapp_available')->nullable()->index();
            }
            if (! Schema::hasColumn('shopify_customers', 'whatsapp_phone')) {
                $table->string('whatsapp_phone', 32)->nullable()->index();
            }
            if (! Schema::hasColumn('shopify_customers', 'whatsapp_checked_at')) {
                $table->timestamp('whatsapp_checked_at')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shopify_customers')) {
            return;
        }

        Schema::table('shopify_customers', function (Blueprint $table) {
            foreach (['whatsapp_checked_at', 'whatsapp_phone', 'whatsapp_available'] as $column) {
                if (Schema::hasColumn('shopify_customers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
