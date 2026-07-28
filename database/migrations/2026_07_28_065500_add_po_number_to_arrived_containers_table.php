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

        if (! Schema::hasColumn('arrived_containers', 'po_number')) {
            Schema::table('arrived_containers', function (Blueprint $table) {
                $table->string('po_number')->nullable()->after('order_link')->index();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('arrived_containers')) {
            return;
        }

        if (Schema::hasColumn('arrived_containers', 'po_number')) {
            Schema::table('arrived_containers', function (Blueprint $table) {
                $table->dropColumn('po_number');
            });
        }
    }
};
