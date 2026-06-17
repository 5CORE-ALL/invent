<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('supplier_remarks') && ! Schema::hasColumn('supplier_remarks', 'created_by')) {
            Schema::table('supplier_remarks', function (Blueprint $table) {
                $table->string('created_by')->nullable()->after('remark');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('supplier_remarks') && Schema::hasColumn('supplier_remarks', 'created_by')) {
            Schema::table('supplier_remarks', function (Blueprint $table) {
                $table->dropColumn('created_by');
            });
        }
    }
};
