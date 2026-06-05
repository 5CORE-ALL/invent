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
            if (!Schema::hasColumn('rfq_forms', 'linked_skus')) {
                $table->json('linked_skus')->nullable()->after('package_dimension');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rfq_forms', function (Blueprint $table) {
            if (Schema::hasColumn('rfq_forms', 'linked_skus')) {
                $table->dropColumn('linked_skus');
            }
        });
    }
};
