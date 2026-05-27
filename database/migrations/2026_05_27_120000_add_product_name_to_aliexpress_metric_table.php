<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('aliexpress_metric')) {
            return;
        }

        if (! Schema::hasColumn('aliexpress_metric', 'product_name')) {
            Schema::table('aliexpress_metric', function (Blueprint $table) {
                $table->string('product_name', 500)->nullable()->after('sku');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('aliexpress_metric') && Schema::hasColumn('aliexpress_metric', 'product_name')) {
            Schema::table('aliexpress_metric', function (Blueprint $table) {
                $table->dropColumn('product_name');
            });
        }
    }
};
