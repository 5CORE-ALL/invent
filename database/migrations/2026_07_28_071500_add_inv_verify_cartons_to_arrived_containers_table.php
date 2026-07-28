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
            if (! Schema::hasColumn('arrived_containers', 'inv_verify_cartons')) {
                $table->json('inv_verify_cartons')->nullable()->after('po_number');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('arrived_containers')) {
            return;
        }

        if (Schema::hasColumn('arrived_containers', 'inv_verify_cartons')) {
            Schema::table('arrived_containers', function (Blueprint $table) {
                $table->dropColumn('inv_verify_cartons');
            });
        }
    }
};
