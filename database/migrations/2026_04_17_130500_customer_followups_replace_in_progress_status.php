<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Align DB with Follow Up CC UI: "In Progress" removed as a selectable status.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_followups')) {
            return;
        }

        DB::table('customer_followups')
            ->where('status', 'In Progress')
            ->update([
                'status' => 'Pending',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Cannot restore which rows were migrated from In Progress.
    }
};
