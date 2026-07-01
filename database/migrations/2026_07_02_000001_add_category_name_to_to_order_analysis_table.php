<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('to_order_analysis')) {
            return;
        }

        Schema::table('to_order_analysis', function (Blueprint $table) {
            if (! Schema::hasColumn('to_order_analysis', 'category_name')) {
                $table->string('category_name', 191)->nullable()->after('supplier_name');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('to_order_analysis')) {
            return;
        }

        Schema::table('to_order_analysis', function (Blueprint $table) {
            if (Schema::hasColumn('to_order_analysis', 'category_name')) {
                $table->dropColumn('category_name');
            }
        });
    }
};
