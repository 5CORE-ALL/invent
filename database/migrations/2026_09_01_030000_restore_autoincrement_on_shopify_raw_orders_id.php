<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shopify_raw_orders') || ! Schema::hasColumn('shopify_raw_orders', 'id')) {
            return;
        }

        $col = collect(DB::select('SHOW COLUMNS FROM shopify_raw_orders WHERE Field = ?', ['id']))->first();
        $extra = strtolower((string) ($col->Extra ?? ''));
        if (str_contains($extra, 'auto_increment')) {
            return;
        }

        // Laravel upsert() does not send `id`. Without AUTO_INCREMENT, new
        // shopify_raw_orders rows fail (SQL 1364) so /shopify stops at the last
        // successful insert date.
        DB::statement('ALTER TABLE shopify_raw_orders MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
    }

    public function down(): void
    {
        // Do not drop AUTO_INCREMENT — the column is required for new inserts.
    }
};
