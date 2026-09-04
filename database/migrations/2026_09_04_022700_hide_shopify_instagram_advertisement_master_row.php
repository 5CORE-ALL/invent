<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('advertisement_master_hidden_rows')) {
            return;
        }

        $key = 'Shopify · Instagram';
        $exists = DB::table('advertisement_master_hidden_rows')->where('channel_key', $key)->exists();
        if ($exists) {
            return;
        }

        DB::table('advertisement_master_hidden_rows')->insert([
            'channel_key' => $key,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('advertisement_master_hidden_rows')) {
            return;
        }

        DB::table('advertisement_master_hidden_rows')
            ->where('channel_key', 'Shopify · Instagram')
            ->delete();
    }
};
