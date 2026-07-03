<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_master', function (Blueprint $table) {
            if (! Schema::hasColumn('product_master', 'mfr_category_ids')) {
                $table->json('mfr_category_ids')->nullable()->after('category_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_master', function (Blueprint $table) {
            if (Schema::hasColumn('product_master', 'mfr_category_ids')) {
                $table->dropColumn('mfr_category_ids');
            }
        });
    }
};
