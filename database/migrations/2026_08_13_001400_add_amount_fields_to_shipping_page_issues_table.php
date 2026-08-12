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
            if (! Schema::hasColumn('shipping_page_issues', 'amount_received')) {
                $table->string('amount_received', 100)->nullable()->after('state');
            }
            if (! Schema::hasColumn('shipping_page_issues', 'amount_paid')) {
                $table->string('amount_paid', 100)->nullable()->after('amount_received');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shipping_page_issues')) {
            return;
        }

        Schema::table('shipping_page_issues', function (Blueprint $table) {
            if (Schema::hasColumn('shipping_page_issues', 'amount_paid')) {
                $table->dropColumn('amount_paid');
            }
            if (Schema::hasColumn('shipping_page_issues', 'amount_received')) {
                $table->dropColumn('amount_received');
            }
        });
    }
};
