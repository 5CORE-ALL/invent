<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_page_exec_assignments')) {
            return;
        }

        DB::table('purchase_page_exec_assignments')->insertOrIgnore([
            'page_key' => 'forecast',
            'assigned_exec' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_page_exec_assignments')) {
            return;
        }

        DB::table('purchase_page_exec_assignments')->where('page_key', 'forecast')->delete();
    }
};
