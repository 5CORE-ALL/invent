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
        Schema::table('rfq_forms', function (Blueprint $table) {
            if (!Schema::hasColumn('rfq_forms', 'report_meta')) {
                $table->json('report_meta')->nullable()->after('linked_skus');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rfq_forms', function (Blueprint $table) {
            if (Schema::hasColumn('rfq_forms', 'report_meta')) {
                $table->dropColumn('report_meta');
            }
        });
    }
};
