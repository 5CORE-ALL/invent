<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('resource_departments')) {
            return;
        }

        // Rename "Management" to "MGMT" (slug stays "management").
        DB::table('resource_departments')->where('slug', 'management')->update(['name' => 'MGMT']);

        // Add the additional departments if they don't already exist (matched by slug).
        $departments = [
            'cc' => 'CC',
            'shipping' => 'Shipping',
            'logistic' => 'Logistic',
            'imports' => 'Imports',
            'sourcing' => 'Sourcing',
            'dispatch' => 'Dispatch',
            'operations' => 'Operations',
            'audit' => 'Audit',
            'seniors' => 'Seniors',
        ];

        $sortOrder = (int) (DB::table('resource_departments')->max('sort_order')) + 1;

        foreach ($departments as $slug => $name) {
            $exists = DB::table('resource_departments')->where('slug', $slug)->exists();
            if (! $exists) {
                DB::table('resource_departments')->insert([
                    'slug' => $slug,
                    'name' => $name,
                    'sort_order' => $sortOrder,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $sortOrder++;
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('resource_departments')) {
            return;
        }

        DB::table('resource_departments')->whereIn('slug', [
            'cc', 'shipping', 'logistic', 'imports', 'sourcing', 'dispatch', 'operations', 'audit', 'seniors',
        ])->delete();

        // Revert MGMT back to Management.
        DB::table('resource_departments')->where('slug', 'management')->update(['name' => 'Management']);
    }
};
