<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_orders') && ! Schema::hasColumn('purchase_orders', 'advance_percent')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->decimal('advance_percent', 8, 2)->nullable()->after('advance_amount');
            });
        }

        if (! Schema::hasTable('supplier_advances')) {
            Schema::create('supplier_advances', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('supplier_id')->index();
                $table->unsignedBigInteger('purchase_order_id')->nullable()->index();
                $table->decimal('advance_percent', 8, 2)->nullable();
                $table->decimal('advance_amount', 14, 2)->nullable();
                $table->decimal('grand_total', 14, 2)->nullable();
                $table->string('currency', 10)->nullable()->default('USD');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->string('created_by_name')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_orders') && Schema::hasColumn('purchase_orders', 'advance_percent')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropColumn('advance_percent');
            });
        }

        Schema::dropIfExists('supplier_advances');
    }
};
