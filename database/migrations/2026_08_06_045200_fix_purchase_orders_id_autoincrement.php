<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_orders')) {
            return;
        }

        $idCol = collect(DB::select('SHOW COLUMNS FROM purchase_orders WHERE Field = ?', ['id']))->first();
        $indexes = collect(DB::select('SHOW INDEX FROM purchase_orders'));

        $hasPrimary = $indexes->contains(fn ($i) => $i->Key_name === 'PRIMARY');
        $isAutoIncrement = $idCol && str_contains(strtolower((string) ($idCol->Extra ?? '')), 'auto_increment');

        if ($hasPrimary && $isAutoIncrement) {
            return;
        }

        $maxId = (int) (DB::table('purchase_orders')->max('id') ?? 0);

        if (! $hasPrimary) {
            DB::statement('ALTER TABLE purchase_orders MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (id)');
        } else {
            DB::statement('ALTER TABLE purchase_orders MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        }

        if ($maxId > 0) {
            DB::statement('ALTER TABLE purchase_orders AUTO_INCREMENT = '.($maxId + 1));
        }
    }

    public function down(): void
    {
        // Irreversible repair; keep schema as-is.
    }
};
