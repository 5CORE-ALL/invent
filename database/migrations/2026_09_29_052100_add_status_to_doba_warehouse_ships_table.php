<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('doba_warehouse_ships') || Schema::hasColumn('doba_warehouse_ships', 'status')) {
            return;
        }

        Schema::table('doba_warehouse_ships', function (Blueprint $table) {
            $table->string('status', 20)->default('done')->after('shipped');
        });

        DB::table('doba_warehouse_ships')->where('shipped', true)->update(['status' => 'done']);
        DB::table('doba_warehouse_ships')->where('shipped', false)->update(['status' => 'canceled']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('doba_warehouse_ships') || ! Schema::hasColumn('doba_warehouse_ships', 'status')) {
            return;
        }

        Schema::table('doba_warehouse_ships', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
