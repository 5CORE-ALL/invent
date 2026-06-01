<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('spare_part_details')) {
            return;
        }
        Schema::table('spare_part_details', function (Blueprint $table) {
            if (! Schema::hasColumn('spare_part_details', 'quantity')) {
                $table->unsignedInteger('quantity')->nullable()->after('msl_part');
            }
            if (! Schema::hasColumn('spare_part_details', 'supplier_id')) {
                // Match legacy suppliers.id type (no FK — avoids errno 150 on mixed int types)
                $table->unsignedInteger('supplier_id')->nullable()->after('quantity')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('spare_part_details')) {
            return;
        }
        Schema::table('spare_part_details', function (Blueprint $table) {
            if (Schema::hasColumn('spare_part_details', 'quantity')) {
                $table->dropColumn('quantity');
            }
            if (Schema::hasColumn('spare_part_details', 'supplier_id')) {
                $table->dropColumn('supplier_id');
            }
        });
    }
};
