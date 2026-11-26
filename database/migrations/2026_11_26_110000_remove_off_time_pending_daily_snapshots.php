<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The 3:30 PM Ohio record must be what was pending at 3:30 PM. Early versions also wrote it from any
 * run after 3:30 PM (for example a manual run at night). Remove those daily rows: keep only the ones
 * captured from 3:30 PM until 10 minutes later, Ohio time.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_fulfillment_pending_snapshots')) {
            return;
        }

        $tz = 'America/New_York';
        $remove = [];
        foreach (DB::table('order_fulfillment_pending_snapshots')->where('kind', 'daily')->get(['id', 'captured_at']) as $row) {
            // captured_at is stored in the app timezone (the model casts it the same way).
            $ohio = Carbon::parse($row->captured_at, config('app.timezone'))->setTimezone($tz);
            $start = $ohio->copy()->setTime(15, 30, 0);
            if ($ohio->lt($start) || $ohio->gte($start->copy()->addMinutes(10))) {
                $remove[] = $row->id;
            }
        }

        if ($remove !== []) {
            DB::table('order_fulfillment_pending_snapshots')->whereIn('id', $remove)->delete();
        }
    }

    public function down(): void
    {
        // Deleted records cannot be restored.
    }
};
