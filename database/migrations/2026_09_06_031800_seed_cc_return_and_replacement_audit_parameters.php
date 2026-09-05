<?php

use App\Support\Audit\DefaultAuditCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_parameters')) {
            return;
        }

        $now = now();

        foreach (['cc_return', 'cc_replacement'] as $module) {
            $sortByCategory = [];
            foreach (DefaultAuditCatalog::parameters($module) as $row) {
                [$code, $label, $desc, $max, $weight, $critical, $category] = $row;
                $exists = DB::table('audit_parameters')
                    ->where('module', $module)
                    ->where('code', $code)
                    ->exists();
                if ($exists) {
                    continue;
                }
                $sortByCategory[$category] = ($sortByCategory[$category] ?? 0) + 1;
                DB::table('audit_parameters')->insert([
                    'module'      => $module,
                    'code'        => $code,
                    'label'       => $label,
                    'description' => $desc,
                    'category'    => $category,
                    'max_score'   => $max,
                    'weight'      => $weight,
                    'is_critical' => $critical,
                    'is_active'   => true,
                    'sort_order'  => $sortByCategory[$category],
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }

            if (! Schema::hasTable('audit_grades') || ! Schema::hasColumn('audit_grades', 'module')) {
                continue;
            }

            foreach (DefaultAuditCatalog::grades() as $g) {
                $exists = DB::table('audit_grades')
                    ->where('module', $module)
                    ->where('grade', $g['grade'])
                    ->exists();
                if ($exists) {
                    continue;
                }
                DB::table('audit_grades')->insert(array_merge($g, [
                    'module'     => $module,
                    'is_active'  => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('audit_parameters')) {
            DB::table('audit_parameters')->whereIn('module', ['cc_return', 'cc_replacement'])->delete();
        }
        if (Schema::hasTable('audit_grades') && Schema::hasColumn('audit_grades', 'module')) {
            DB::table('audit_grades')->whereIn('module', ['cc_return', 'cc_replacement'])->delete();
        }
    }
};
