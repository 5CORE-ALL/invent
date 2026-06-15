<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds two columns to customer_followups so the grid can keep showing the
 * *original* executive (the user who created the ticket) even after another
 * user edits it, while a hover tooltip shows the full edit history.
 *
 *   - original_executive : set once at create time; never overwritten on edit.
 *   - executive_history  : JSON array of {name, at, action} entries appended
 *                          on every meaningful save (created / updated /
 *                          status_changed). Consecutive identical entries by
 *                          the same user collapse to one row (timestamp
 *                          bumped) so a noisy double-click doesn't pollute
 *                          the audit trail.
 *
 * Backfills both columns from the existing assigned_executive so older rows
 * render the same as before once the migration runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_followups')) {
            return;
        }

        Schema::table('customer_followups', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_followups', 'original_executive')) {
                $table->string('original_executive')->nullable()->after('assigned_executive');
            }
            if (! Schema::hasColumn('customer_followups', 'executive_history')) {
                $table->json('executive_history')->nullable()->after('original_executive');
            }
        });

        // Backfill: original_executive ← assigned_executive; seed history with
        // a single 'created' entry so the tooltip renders something useful
        // even before the row is touched again.
        DB::table('customer_followups')
            ->whereNull('original_executive')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $exec = $row->assigned_executive !== null ? trim((string) $row->assigned_executive) : '';
                    $createdAt = $row->created_at ?? null;
                    $atIso = $createdAt
                        ? Carbon::parse($createdAt)->toIso8601String()
                        : Carbon::now()->toIso8601String();

                    $history = $exec === '' ? [] : [[
                        'name'   => $exec,
                        'at'     => $atIso,
                        'action' => 'created',
                    ]];

                    DB::table('customer_followups')
                        ->where('id', $row->id)
                        ->update([
                            'original_executive' => $exec === '' ? null : $exec,
                            'executive_history'  => $history === [] ? null : json_encode($history, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('customer_followups')) {
            return;
        }

        Schema::table('customer_followups', function (Blueprint $table) {
            if (Schema::hasColumn('customer_followups', 'executive_history')) {
                $table->dropColumn('executive_history');
            }
            if (Schema::hasColumn('customer_followups', 'original_executive')) {
                $table->dropColumn('original_executive');
            }
        });
    }
};
