<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if user_id column exists before trying to query it
        $columns = DB::select('DESCRIBE permissions');
        $hasUserId = collect($columns)->contains('Field', 'user_id');

        if ($hasUserId) {
            // Migrate existing user permissions to user roles
            $userPermissions = DB::table('permissions')
                ->whereNotNull('user_id')
                ->get();

            foreach ($userPermissions as $permission) {
                DB::table('users')
                    ->where('id', $permission->user_id)
                    ->update(['role' => $permission->role]);
            }

            // Remove user-specific permission records (keep only role-based ones)
            DB::table('permissions')
                ->whereNotNull('user_id')
                ->delete();

            // Drop user_id column
            Schema::table('permissions', function (Blueprint $table) {
                $table->dropColumn('user_id');
            });
        }

        // Ensure all users have a default role if not set
        DB::table('users')
            ->whereNull('role')
            ->orWhere('role', '')
            ->update(['role' => 'user']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back user_id column to permissions table if it doesn't exist
        $columns = DB::select('DESCRIBE permissions');
        $hasUserId = collect($columns)->contains('Field', 'user_id');

        if (!$hasUserId) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('role');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }
};
