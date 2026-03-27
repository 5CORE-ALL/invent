<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE tasks MODIFY COLUMN etc_done BIGINT NULL');
        DB::statement('ALTER TABLE deleted_tasks MODIFY COLUMN etc_done BIGINT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tasks MODIFY COLUMN etc_done INT NULL');
        DB::statement('ALTER TABLE deleted_tasks MODIFY COLUMN etc_done INT NULL');
    }
};

