<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a hard FK link to users so the Executive column can render the
 * creator's *current* `users.name` instead of whatever free-text was stamped
 * into `original_executive` at the time of writing.
 *
 * Why this exists: `original_executive` was a snapshot string that drifted
 * (same person stored as "Hritiksha" and "Hritiksha Deb", or "Sounak" /
 * "Sounak B") because users renamed themselves over time and because the
 * 6/16 backfill seeded `original_executive` from `assigned_executive` (the
 * *last* editor) for rows that pre-dated the column. With a real FK we can
 * collapse those duplicates to one canonical display name.
 *
 * Backfill strategy (best-effort, never destructive):
 *   1. Try an exact case-insensitive match on `users.name`.
 *   2. If that fails, try a prefix match (so "Hritiksha Deb" still maps to
 *      user "Hritiksha") — but only when exactly one user matches, to avoid
 *      mis-attributing tickets when two users share a first name.
 *   3. Otherwise leave `created_by_user_id` NULL; the controller falls back
 *      to the existing `original_executive` string for those rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_followups')) {
            return;
        }

        Schema::table('customer_followups', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_followups', 'created_by_user_id')) {
                $table->unsignedBigInteger('created_by_user_id')->nullable()->after('original_executive');
                $table->index('created_by_user_id', 'cf_created_by_user_id_idx');
            }
        });

        if (! Schema::hasTable('users')) {
            return;
        }

        $users = DB::table('users')
            ->whereNull('deleted_at')
            ->get(['id', 'name']);

        if ($users->isEmpty()) {
            return;
        }

        $byExactName = [];
        foreach ($users as $u) {
            $key = mb_strtolower(trim((string) $u->name));
            if ($key === '') {
                continue;
            }
            $byExactName[$key] = (int) $u->id;
        }

        DB::table('customer_followups')
            ->whereNull('created_by_user_id')
            ->whereNotNull('original_executive')
            ->where('original_executive', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($byExactName, $users) {
                foreach ($rows as $row) {
                    $needle = mb_strtolower(trim((string) $row->original_executive));
                    if ($needle === '') {
                        continue;
                    }

                    $userId = $byExactName[$needle] ?? null;

                    if ($userId === null) {
                        $candidates = [];
                        foreach ($users as $u) {
                            $uname = mb_strtolower(trim((string) $u->name));
                            if ($uname === '') {
                                continue;
                            }
                            if (str_starts_with($needle, $uname . ' ') || str_starts_with($uname, $needle . ' ')) {
                                $candidates[] = (int) $u->id;
                            }
                        }
                        if (count($candidates) === 1) {
                            $userId = $candidates[0];
                        }
                    }

                    if ($userId !== null) {
                        DB::table('customer_followups')
                            ->where('id', $row->id)
                            ->update(['created_by_user_id' => $userId]);
                    }
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('customer_followups')) {
            return;
        }

        Schema::table('customer_followups', function (Blueprint $table) {
            if (Schema::hasColumn('customer_followups', 'created_by_user_id')) {
                try {
                    $table->dropIndex('cf_created_by_user_id_idx');
                } catch (\Throwable $e) {
                    // Index may not exist if up() bailed early; ignore.
                }
                $table->dropColumn('created_by_user_id');
            }
        });
    }
};
