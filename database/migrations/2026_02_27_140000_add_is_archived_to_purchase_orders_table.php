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
        if (! Schema::hasTable('purchase_orders') || Schema::hasColumn('purchase_orders', 'is_archived')) {
            return;
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->boolean('is_archived')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('purchase_orders') || ! Schema::hasColumn('purchase_orders', 'is_archived')) {
            return;
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('is_archived');
        });
    }
};
