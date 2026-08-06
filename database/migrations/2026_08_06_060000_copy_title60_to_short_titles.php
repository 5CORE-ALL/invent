<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-time: copy product_master.title60 into short_titles.short_title (Short name).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_master') || ! Schema::hasTable('short_titles')) {
            return;
        }
        if (! Schema::hasColumn('product_master', 'title60')) {
            return;
        }

        // Ensure short_titles.id can accept inserts (repair missing AUTO_INCREMENT).
        $idCol = collect(DB::select('SHOW COLUMNS FROM short_titles WHERE Field = ?', ['id']))->first();
        $indexes = collect(DB::select('SHOW INDEX FROM short_titles'));
        $hasPrimary = $indexes->contains(fn ($i) => $i->Key_name === 'PRIMARY');
        $isAutoIncrement = $idCol && str_contains(strtolower((string) ($idCol->Extra ?? '')), 'auto_increment');
        if (! $hasPrimary || ! $isAutoIncrement) {
            if (! $hasPrimary) {
                DB::statement('ALTER TABLE short_titles MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (id)');
            } else {
                DB::statement('ALTER TABLE short_titles MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
            }
        }

        $now = now();

        DB::table('product_master')
            ->whereNull('deleted_at')
            ->whereNotNull('title60')
            ->whereRaw('TRIM(title60) != ""')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->where('sku', 'NOT LIKE', 'PARENT %')
            ->orderBy('id')
            ->select(['id', 'sku', 'title60'])
            ->chunkById(500, function ($rows) use ($now) {
                foreach ($rows as $row) {
                    $sku = trim((string) ($row->sku ?? ''));
                    $title = trim((string) ($row->title60 ?? ''));
                    if ($sku === '' || $title === '') {
                        continue;
                    }

                    $existing = DB::table('short_titles')->where('sku', $sku)->first();
                    if ($existing) {
                        DB::table('short_titles')->where('sku', $sku)->update([
                            'short_title' => $title,
                            'updated_at' => $now,
                        ]);
                    } else {
                        DB::table('short_titles')->insert([
                            'sku' => $sku,
                            'short_title' => $title,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        // One-time data copy — no reverse.
    }
};
