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
        Schema::table('inventories', function (Blueprint $table) {
            if (!Schema::hasColumn('inventories', 'is_verified')) {
                $table->boolean('is_verified')->default(false)->after('is_approved');
            }
            if (!Schema::hasColumn('inventories', 'is_doubtful')) {
                $table->boolean('is_doubtful')->default(false)->after('is_verified');
            }
            if (!Schema::hasColumn('inventories', 'verified_by')) {
                // Add column without foreign key constraint to avoid migration errors
                // The Eloquent relationship will still work correctly
                $table->unsignedBigInteger('verified_by')->nullable()->after('is_verified');
            }
        });
        
        // Try to add foreign key constraint if users table has proper primary key
        // This is optional - the column will work without it
        try {
            if (Schema::hasTable('users') && Schema::hasColumn('inventories', 'verified_by')) {
                // Check if users.id is a primary key
                $hasPrimaryKey = DB::select("
                    SELECT COUNT(*) as count 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'users' 
                    AND COLUMN_NAME = 'id' 
                    AND CONSTRAINT_NAME = 'PRIMARY'
                ");
                
                if (!empty($hasPrimaryKey) && $hasPrimaryKey[0]->count > 0) {
                    // Check if foreign key already exists
                    $foreignKeyExists = DB::select("
                        SELECT COUNT(*) as count 
                        FROM information_schema.KEY_COLUMN_USAGE 
                        WHERE TABLE_SCHEMA = DATABASE() 
                        AND TABLE_NAME = 'inventories' 
                        AND CONSTRAINT_NAME = 'inventories_verified_by_foreign'
                    ");
                    
                    if (empty($foreignKeyExists) || $foreignKeyExists[0]->count == 0) {
                        Schema::table('inventories', function (Blueprint $table) {
                            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
                        });
                    }
                }
            }
        } catch (\Exception $e) {
            // If foreign key creation fails, continue without it
            // The column and Eloquent relationship will still work
            \Log::info('Foreign key constraint for verified_by skipped: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            if (Schema::hasColumn('inventories', 'verified_by')) {
                // Try to drop foreign key if it exists
                try {
                    $table->dropForeign(['verified_by']);
                } catch (\Exception $e) {
                    // Foreign key might not exist, continue
                }
                $table->dropColumn('verified_by');
            }
            if (Schema::hasColumn('inventories', 'is_doubtful')) {
                $table->dropColumn('is_doubtful');
            }
            if (Schema::hasColumn('inventories', 'is_verified')) {
                $table->dropColumn('is_verified');
            }
        });
    }
};
