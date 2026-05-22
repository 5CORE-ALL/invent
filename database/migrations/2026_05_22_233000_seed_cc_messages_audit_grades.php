<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Seed module-specific grade bands for the cc_messages audit and recompute
     * the grade letter on every existing cc_messages audit so the new bands are
     * reflected throughout the UI immediately.
     *
     * Bands (single-letter grades — the score-card UI is keyed off these):
     *   A  >= 95          Excellent          green   #28a745
     *   B  90 - 94.99     Good               blue    #0d6efd
     *   C  80 - 89.99     Needs Improvement  yellow  #ffc107
     *   F  <  80          Critical           red     #dc3545
     *
     * Idempotent — safe to re-run.
     */
    public function up(): void
    {
        if (! Schema::hasTable('audit_grades') || ! Schema::hasTable('audit_results')) {
            return;
        }

        $module = 'cc_messages';
        $now    = now();

        $grades = [
            ['grade' => 'A', 'min_score' => 95,    'max_score' => 120.00, 'color' => '#28a745', 'description' => 'Excellent',         'sort_order' => 1],
            ['grade' => 'B', 'min_score' => 90,    'max_score' => 94.99,  'color' => '#0d6efd', 'description' => 'Good',              'sort_order' => 2],
            ['grade' => 'C', 'min_score' => 80,    'max_score' => 89.99,  'color' => '#ffc107', 'description' => 'Needs Improvement', 'sort_order' => 3],
            ['grade' => 'F', 'min_score' => 0,     'max_score' => 79.99,  'color' => '#dc3545', 'description' => 'Critical',          'sort_order' => 4],
        ];

        foreach ($grades as $g) {
            DB::table('audit_grades')->updateOrInsert(
                ['module' => $module, 'grade' => $g['grade']],
                array_merge($g, [
                    'module'     => $module,
                    'is_active'  => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ])
            );
        }

        // Deactivate any other (legacy) grade rows for cc_messages so they don't
        // sneak back in via lookups — e.g. an old 'A+' or 'D' band.
        DB::table('audit_grades')
            ->where('module', $module)
            ->whereNotIn('grade', array_column($grades, 'grade'))
            ->update(['is_active' => false, 'updated_at' => $now]);

        // Recompute the grade letter on existing cc_messages audits to match the
        // new bands. Rows already flagged with has_critical_failure stay on 'F'.
        $audits = DB::table('audit_results')
            ->where('module', $module)
            ->get(['id', 'total_score', 'has_critical_failure', 'grade']);

        foreach ($audits as $row) {
            $newGrade = $this->resolveGrade((float) $row->total_score, (bool) $row->has_critical_failure);
            if ($newGrade !== null && $newGrade !== $row->grade) {
                DB::table('audit_results')
                    ->where('id', $row->id)
                    ->update(['grade' => $newGrade, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('audit_grades')) {
            DB::table('audit_grades')->where('module', 'cc_messages')->delete();
        }
    }

    private function resolveGrade(float $score, bool $hasCriticalFailure): ?string
    {
        if ($hasCriticalFailure) return 'F';
        if ($score >= 95)        return 'A';
        if ($score >= 90)        return 'B';
        if ($score >= 80)        return 'C';
        return 'F';
    }
};
