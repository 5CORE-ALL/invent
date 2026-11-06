<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each eBay account (ebay1 / ebay2 / ebay3) keeps its own Listing Quality Report upload.
 * Everything uploaded before this belongs to eBay 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('excel_imports') && ! Schema::hasColumn('excel_imports', 'account')) {
            Schema::table('excel_imports', function (Blueprint $table) {
                $table->string('account', 10)->default('ebay1')->after('uploaded_by')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('excel_imports') && Schema::hasColumn('excel_imports', 'account')) {
            Schema::table('excel_imports', function (Blueprint $table) {
                $table->dropColumn('account');
            });
        }
    }
};
