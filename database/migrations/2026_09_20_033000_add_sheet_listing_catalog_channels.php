<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vinted_listing_statuses')) {
            Schema::create('vinted_listing_statuses', function (Blueprint $table) {
                $table->id();
                $table->string('sku')->index();
                $table->json('value');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('channel_master')) {
            return;
        }

        $now = now();
        foreach ([
            ['channel' => 'Vinted', 'sheet' => '/vinted/sheet'],
            ['channel' => 'DHGate', 'sheet' => null],
            ['channel' => 'Tiendamia', 'sheet' => null],
        ] as $row) {
            $exists = DB::table('channel_master')
                ->whereRaw('LOWER(TRIM(channel)) = ?', [strtolower($row['channel'])])
                ->exists();
            if ($exists) {
                continue;
            }
            $insert = [
                'channel' => $row['channel'],
                'status' => 'Active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (Schema::hasColumn('channel_master', 'sheet_link') && $row['sheet']) {
                $insert['sheet_link'] = $row['sheet'];
            }
            if (Schema::hasColumn('channel_master', 'type')) {
                $insert['type'] = 'B2C';
            }
            DB::table('channel_master')->insert($insert);
        }

        if (Schema::hasColumn('channel_master', 'listing_mode')) {
            foreach (['depop', 'vinted', 'dhgate', 'tiendamia'] as $slug) {
                DB::table('channel_master')
                    ->whereRaw('LOWER(TRIM(channel)) = ?', [$slug])
                    ->where(function ($q) {
                        $q->whereNull('listing_mode')->orWhere('listing_mode', '');
                    })
                    ->update(['listing_mode' => 'CSV']);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vinted_listing_statuses');
    }
};
