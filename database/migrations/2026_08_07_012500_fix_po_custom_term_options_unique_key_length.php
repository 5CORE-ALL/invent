<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('po_custom_term_options')) {
            return;
        }

        // Drop unique index if a prior attempt created/left a broken one.
        try {
            Schema::table('po_custom_term_options', function (Blueprint $table) {
                $table->dropUnique('po_custom_term_options_value_unique');
            });
        } catch (\Throwable $e) {
            // Index may not exist yet.
        }

        // utf8mb4 unique index max is 768 chars (3072 bytes).
        DB::statement('ALTER TABLE `po_custom_term_options` MODIFY `value` VARCHAR(768) NOT NULL');

        $hasUnique = collect(DB::select('SHOW INDEX FROM `po_custom_term_options`'))
            ->contains(fn ($idx) => ($idx->Key_name ?? '') === 'po_custom_term_options_value_unique');

        if (! $hasUnique) {
            Schema::table('po_custom_term_options', function (Blueprint $table) {
                $table->unique('value');
            });
        }
    }

    public function down(): void
    {
        // No-op: keeping the shorter unique-safe column is safer.
    }
};
