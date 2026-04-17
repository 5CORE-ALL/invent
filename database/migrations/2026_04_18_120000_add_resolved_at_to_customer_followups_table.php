<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customer_followups')) {
            return;
        }

        Schema::table('customer_followups', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_followups', 'resolved_at')) {
                $table->dateTime('resolved_at')->nullable();
            }
        });

        // Approximate historical resolve time (best-effort for rows before tracking).
        if (Schema::hasColumn('customer_followups', 'resolved_at')) {
            DB::table('customer_followups')
                ->where('status', 'Resolved')
                ->whereNull('resolved_at')
                ->update(['resolved_at' => DB::raw('updated_at')]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('customer_followups')) {
            return;
        }

        Schema::table('customer_followups', function (Blueprint $table) {
            if (Schema::hasColumn('customer_followups', 'resolved_at')) {
                $table->dropColumn('resolved_at');
            }
        });
    }
};
