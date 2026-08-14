<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * advertisement_master_metric_snapshots.id was created as a PK without
 * AUTO_INCREMENT, so every snapshot insert failed with
 * "Field 'id' doesn't have a default value" and the page history stayed empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('advertisement_master_metric_snapshots')) {
            return;
        }

        DB::statement('ALTER TABLE advertisement_master_metric_snapshots MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
    }

    public function down(): void
    {
        // Keep AUTO_INCREMENT; reverting would break inserts again.
    }
};
