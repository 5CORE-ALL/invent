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
            if (! Schema::hasColumn('shipping_page_issues', 'zone')) {
                $table->string('zone', 20)->nullable()->after('pin_code');
            }
            if (! Schema::hasColumn('shipping_page_issues', 'state')) {
                $table->string('state', 100)->nullable()->after('zone');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shipping_page_issues')) {
            return;
        }

        Schema::table('shipping_page_issues', function (Blueprint $table) {
            if (Schema::hasColumn('shipping_page_issues', 'state')) {
                $table->dropColumn('state');
            }
            if (Schema::hasColumn('shipping_page_issues', 'zone')) {
                $table->dropColumn('zone');
            }
        });
    }
};
