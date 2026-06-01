<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reverb_products')) {
            return;
        }

        if (! Schema::hasColumn('reverb_products', 'description')) {
            Schema::table('reverb_products', function (Blueprint $table) {
                $table->text('description')->nullable()->after('product_title');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('reverb_products') && Schema::hasColumn('reverb_products', 'description')) {
            Schema::table('reverb_products', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
    }
};
