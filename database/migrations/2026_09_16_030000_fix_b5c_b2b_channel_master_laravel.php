<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('channel_master')) {
            return;
        }

        $query = DB::table('channel_master')
            ->whereRaw('LOWER(TRIM(channel)) = ?', ['business 5 core (b2b)']);

        $update = [
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('channel_master', 'logo')) {
            $update['logo'] = 'uploads/laravel.svg';
        }
        if (Schema::hasColumn('channel_master', 'type')) {
            $update['type'] = 'API';
        }

        $query->update($update);
    }

    public function down(): void
    {
    }
};
