<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Assuming the user with ID 1 exists (created in DatabaseSeeder)
        DB::table('permissions')->insert([
            'user_id' => 1,
            'role' => 'admin',
            'permissions' => json_encode(['permissions' => ['view', 'create', 'edit', 'delete']]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}