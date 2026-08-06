<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_bank_accounts')) {
            return;
        }

        // Table was created without PRIMARY KEY / AUTO_INCREMENT on id.
        $columns = collect(DB::select('SHOW COLUMNS FROM supplier_bank_accounts'))
            ->keyBy(fn ($c) => $c->Field);

        $id = $columns->get('id');
        if (! $id) {
            return;
        }

        $extra = strtolower((string) ($id->Extra ?? ''));
        $key = strtoupper((string) ($id->Key ?? ''));
        if (str_contains($extra, 'auto_increment') && $key === 'PRI') {
            return;
        }

        // Empty table is safe; if rows exist, keep max(id) sequence.
        $maxId = (int) (DB::table('supplier_bank_accounts')->max('id') ?? 0);

        if ($key !== 'PRI') {
            // Drop accidental non-primary indexes named id if any, then add PK.
            try {
                DB::statement('ALTER TABLE supplier_bank_accounts ADD PRIMARY KEY (id)');
            } catch (\Throwable $e) {
                // Already has a primary key under another definition.
            }
        }

        DB::statement('ALTER TABLE supplier_bank_accounts MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');

        if ($maxId > 0) {
            DB::statement('ALTER TABLE supplier_bank_accounts AUTO_INCREMENT = '.($maxId + 1));
        }
    }

    public function down(): void
    {
        // Non-destructive: leave AUTO_INCREMENT in place.
    }
};
