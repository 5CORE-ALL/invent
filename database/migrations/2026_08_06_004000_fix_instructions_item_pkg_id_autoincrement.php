<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('instructions_item_pkg')) {
            return;
        }

        $idCol = collect(DB::select('SHOW COLUMNS FROM instructions_item_pkg WHERE Field = ?', ['id']))->first();
        $indexes = collect(DB::select('SHOW INDEX FROM instructions_item_pkg'));

        $hasPrimary = $indexes->contains(fn ($i) => $i->Key_name === 'PRIMARY');
        $isAutoIncrement = $idCol && str_contains(strtolower((string) ($idCol->Extra ?? '')), 'auto_increment');
        $hasProductUnique = $indexes->contains(
            fn ($i) => $i->Column_name === 'product_master_id' && (int) $i->Non_unique === 0
        );

        if (! $hasPrimary || ! $isAutoIncrement) {
            if (! $hasPrimary) {
                DB::statement('ALTER TABLE instructions_item_pkg MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (id)');
            } else {
                DB::statement('ALTER TABLE instructions_item_pkg MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
            }
        }

        if (! $hasProductUnique) {
            DB::statement('ALTER TABLE instructions_item_pkg ADD UNIQUE KEY instructions_item_pkg_product_master_id_unique (product_master_id)');
        }
    }

    public function down(): void
    {
        // Irreversible repair; keep schema as-is.
    }
};
