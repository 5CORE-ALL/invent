<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('comparison_data')) {
            return;
        }

        $idCol = collect(DB::select('SHOW COLUMNS FROM comparison_data WHERE Field = ?', ['id']))->first();
        $indexes = collect(DB::select('SHOW INDEX FROM comparison_data'));

        $hasPrimary = $indexes->contains(fn ($i) => $i->Key_name === 'PRIMARY');
        $isAutoIncrement = $idCol && str_contains(strtolower((string) ($idCol->Extra ?? '')), 'auto_increment');

        if ($hasPrimary && $isAutoIncrement) {
            return;
        }

        $maxId = (int) (DB::table('comparison_data')->max('id') ?? 0);

        if (! $hasPrimary) {
            DB::statement('ALTER TABLE comparison_data MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (id)');
        } else {
            DB::statement('ALTER TABLE comparison_data MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        }

        if ($maxId > 0) {
            DB::statement('ALTER TABLE comparison_data AUTO_INCREMENT = '.($maxId + 1));
        }
    }

    public function down(): void
    {
        // Irreversible repair; keep schema as-is.
    }
};
