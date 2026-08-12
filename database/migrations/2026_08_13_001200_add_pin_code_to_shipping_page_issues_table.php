<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipping_page_issues')) {
            return;
        }

        Schema::table('shipping_page_issues', function (Blueprint $table) {
            if (! Schema::hasColumn('shipping_page_issues', 'pin_code')) {
                $table->string('pin_code', 20)->nullable()->after('sku');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shipping_page_issues')) {
            return;
        }

        Schema::table('shipping_page_issues', function (Blueprint $table) {
            if (Schema::hasColumn('shipping_page_issues', 'pin_code')) {
                $table->dropColumn('pin_code');
            }
        });
    }
};
