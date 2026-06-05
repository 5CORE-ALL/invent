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
            if (!Schema::hasColumn('rfq_forms', 'created_by')) {
                $table->string('created_by')->nullable()->after('package_dimension');
            }
            if (!Schema::hasColumn('rfq_forms', 'updated_by')) {
                $table->string('updated_by')->nullable()->after('created_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rfq_forms', function (Blueprint $table) {
            if (Schema::hasColumn('rfq_forms', 'created_by')) {
                $table->dropColumn('created_by');
            }
            if (Schema::hasColumn('rfq_forms', 'updated_by')) {
                $table->dropColumn('updated_by');
            }
        });
    }
};
