<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('short_titles')) {
            return;
        }

        $idCol = collect(DB::select('SHOW COLUMNS FROM short_titles WHERE Field = ?', ['id']))->first();
        $indexes = collect(DB::select('SHOW INDEX FROM short_titles'));

        $hasPrimary = $indexes->contains(fn ($i) => $i->Key_name === 'PRIMARY');
        $isAutoIncrement = $idCol && str_contains(strtolower((string) ($idCol->Extra ?? '')), 'auto_increment');
        $hasSkuUnique = $indexes->contains(fn ($i) => $i->Column_name === 'sku' && (int) $i->Non_unique === 0);

        if (! $hasPrimary || ! $isAutoIncrement) {
            if (! $hasPrimary) {
                DB::statement('ALTER TABLE short_titles MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (id)');
            } else {
                DB::statement('ALTER TABLE short_titles MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
            }
        }

        if (! $hasSkuUnique) {
            DB::statement('ALTER TABLE short_titles ADD UNIQUE KEY short_titles_sku_unique (sku)');
        }
    }

    public function down(): void
    {
        // Irreversible repair; keep schema as-is.
    }
};
