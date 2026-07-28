<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('arrived_containers')) {
            return;
        }

        Schema::table('arrived_containers', function (Blueprint $table) {
            if (! Schema::hasColumn('arrived_containers', 'cp_approved')) {
                $table->string('cp_approved', 10)->nullable()->after('po_number');
            }
            if (! Schema::hasColumn('arrived_containers', 'cp_approved_reason')) {
                $table->string('cp_approved_reason', 255)->nullable()->after('cp_approved');
            }
            if (! Schema::hasColumn('arrived_containers', 'cp_approved_auto')) {
                $table->boolean('cp_approved_auto')->default(false)->after('cp_approved_reason');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('arrived_containers')) {
            return;
        }

        Schema::table('arrived_containers', function (Blueprint $table) {
            foreach (['cp_approved_auto', 'cp_approved_reason', 'cp_approved'] as $col) {
                if (Schema::hasColumn('arrived_containers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
