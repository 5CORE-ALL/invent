<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_orders') && ! Schema::hasColumn('purchase_orders', 'po_stage')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->string('po_stage', 30)->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_orders') && Schema::hasColumn('purchase_orders', 'po_stage')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropIndex(['po_stage']);
                $table->dropColumn('po_stage');
            });
        }
    }
};
