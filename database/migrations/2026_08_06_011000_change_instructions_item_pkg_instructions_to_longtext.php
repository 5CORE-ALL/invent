<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove practical length cap on Instructions item PKG (was TEXT / app-truncated to 2000).
     */
    public function up(): void
    {
        if (! Schema::hasTable('instructions_item_pkg')) {
            return;
        }

        DB::statement('ALTER TABLE instructions_item_pkg MODIFY instructions LONGTEXT NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('instructions_item_pkg')) {
            return;
        }

        DB::statement('ALTER TABLE instructions_item_pkg MODIFY instructions TEXT NULL');
    }
};
