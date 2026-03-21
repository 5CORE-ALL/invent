<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_master', function (Blueprint $table) {
            if (! Schema::hasColumn('product_master', 'barcode')) {
                $table->string('barcode', 64)->nullable()->after('sku');
                $table->unique('barcode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_master', function (Blueprint $table) {
            if (Schema::hasColumn('product_master', 'barcode')) {
                $table->dropUnique(['barcode']);
                $table->dropColumn('barcode');
            }
        });
    }
};
