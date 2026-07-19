<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensure Vinted channel_master points Sheet → sales and Missing → pricing.
     */
    public function up(): void
    {
        if (! Schema::hasTable('channel_master')) {
            return;
        }

        $updates = [];
        if (Schema::hasColumn('channel_master', 'sheet_link')) {
            $updates['sheet_link'] = '/vinted/sheet';
        }
        if (Schema::hasColumn('channel_master', 'missing_link')) {
            $updates['missing_link'] = '/vinted/pricing';
        }
        if (empty($updates)) {
            return;
        }

        $updates['updated_at'] = now();

        DB::table('channel_master')
            ->whereRaw('LOWER(TRIM(channel)) = ?', ['vinted'])
            ->update($updates);
    }

    public function down(): void
    {
        // no-op — links may have been set intentionally
    }
};
