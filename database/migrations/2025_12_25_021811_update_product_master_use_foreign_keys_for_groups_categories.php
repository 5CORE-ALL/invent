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
        // First ensure product_groups and product_categories tables exist
        if (!Schema::hasTable('product_groups')) {
            throw new \Exception('product_groups table must exist before running this migration');
        }
        
        if (!Schema::hasTable('product_categories')) {
            throw new \Exception('product_categories table must exist before running this migration');
        }
        
        // Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        Schema::table('product_master', function (Blueprint $table) {
            // Add category_id column if it doesn't exist
            if (!Schema::hasColumn('product_master', 'category_id')) {
                $table->unsignedBigInteger('category_id')->nullable()->after('category');
            }
            
            // Add foreign key for category_id (check if it doesn't already exist)
            if (Schema::hasColumn('product_master', 'category_id')) {
                $existingFk = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'product_master' 
                    AND COLUMN_NAME = 'category_id' 
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");
                if (empty($existingFk)) {
                    $table->foreign('category_id')
                        ->references('id')
                        ->on('product_categories')
                        ->onDelete('set null');
                }
            }
            
            // Update group_id to be a foreign key if it exists
            if (Schema::hasColumn('product_master', 'group_id')) {
                // Check if foreign key exists and drop it if it references wrong table
                $existingFk = DB::select("
                    SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'product_master' 
                    AND COLUMN_NAME = 'group_id' 
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");
                if (!empty($existingFk)) {
                    $fkName = $existingFk[0]->CONSTRAINT_NAME;
                    if ($existingFk[0]->REFERENCED_TABLE_NAME !== 'product_groups') {
                        $table->dropForeign([$fkName]);
                    } else {
                        // Foreign key already points to product_groups, skip
                        return;
                    }
                }
                // Add new foreign key
                $table->foreign('group_id')
                    ->references('id')
                    ->on('product_groups')
                    ->onDelete('set null');
            } else {
                // Add group_id as foreign key if it doesn't exist
                $table->unsignedBigInteger('group_id')->nullable()->after('group');
                $table->foreign('group_id')
                    ->references('id')
                    ->on('product_groups')
                    ->onDelete('set null');
            }
        });
        
        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_master', function (Blueprint $table) {
            // Drop foreign keys
            if (Schema::hasColumn('product_master', 'category_id')) {
                $existingFk = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'product_master' 
                    AND COLUMN_NAME = 'category_id' 
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");
                if (!empty($existingFk)) {
                    $table->dropForeign([$existingFk[0]->CONSTRAINT_NAME]);
                }
            }
            if (Schema::hasColumn('product_master', 'group_id')) {
                $existingFk = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'product_master' 
                    AND COLUMN_NAME = 'group_id' 
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");
                if (!empty($existingFk)) {
                    $table->dropForeign([$existingFk[0]->CONSTRAINT_NAME]);
                }
            }
        });
    }
};
