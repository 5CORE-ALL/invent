<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_master', function (Blueprint $table) {
            if (! Schema::hasColumn('product_master', 'description_1500')) {
                $table->longText('description_1500')->nullable()->after('product_description');
            }
            if (! Schema::hasColumn('product_master', 'description_1000')) {
                $table->longText('description_1000')->nullable()->after('description_1500');
            }
            if (! Schema::hasColumn('product_master', 'description_800')) {
                $table->longText('description_800')->nullable()->after('description_1000');
            }
            if (! Schema::hasColumn('product_master', 'description_600')) {
                $table->longText('description_600')->nullable()->after('description_800');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_master', function (Blueprint $table) {
            foreach (['description_1500', 'description_1000', 'description_800', 'description_600'] as $col) {
                if (Schema::hasColumn('product_master', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
