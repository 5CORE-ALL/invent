<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_bank_account_histories')) {
            return;
        }

        $columns = collect(DB::select('SHOW COLUMNS FROM supplier_bank_account_histories'))
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

        $maxId = (int) (DB::table('supplier_bank_account_histories')->max('id') ?? 0);

        if ($key !== 'PRI') {
            try {
                DB::statement('ALTER TABLE supplier_bank_account_histories ADD PRIMARY KEY (id)');
            } catch (\Throwable $e) {
                // already has PK
            }
        }

        DB::statement('ALTER TABLE supplier_bank_account_histories MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');

        if ($maxId > 0) {
            DB::statement('ALTER TABLE supplier_bank_account_histories AUTO_INCREMENT = '.($maxId + 1));
        }
    }

    public function down(): void
    {
        // leave AUTO_INCREMENT
    }
};
