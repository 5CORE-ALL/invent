<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cjdropshipping_listing_statuses')) {
            Schema::create('cjdropshipping_listing_statuses', function (Blueprint $table) {
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
        $existing = DB::table('channel_master')
            ->whereRaw("LOWER(REPLACE(REPLACE(REPLACE(TRIM(channel), ' ', ''), '-', ''), '_', '')) = ?", ['cjdropshipping'])
            ->first();

        if ($existing) {
            $update = ['status' => 'Active', 'updated_at' => $now];
            if (Schema::hasColumn('channel_master', 'listing_mode') && trim((string) ($existing->listing_mode ?? '')) === '') {
                $update['listing_mode'] = 'CSV';
            }
            if (Schema::hasColumn('channel_master', 'type') && trim((string) ($existing->type ?? '')) === '') {
                $update['type'] = 'Dropship';
            }
            if (Schema::hasColumn('channel_master', 'seller_link') && trim((string) ($existing->seller_link ?? '')) === '') {
                $update['seller_link'] = 'https://cjdropshipping.com/';
            }
            if (Schema::hasColumn('channel_master', 'missing_link') && trim((string) ($existing->missing_link ?? '')) === '') {
                $update['missing_link'] = '/listing-cjdropshipping';
            }
            DB::table('channel_master')->where('id', $existing->id)->update($update);
        } else {
            $insert = [
                'channel' => 'CJdropshipping',
                'status' => 'Active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (Schema::hasColumn('channel_master', 'type')) {
                $insert['type'] = 'Dropship';
            }
            if (Schema::hasColumn('channel_master', 'listing_mode')) {
                $insert['listing_mode'] = 'CSV';
            }
            if (Schema::hasColumn('channel_master', 'seller_link')) {
                $insert['seller_link'] = 'https://cjdropshipping.com/';
            }
            if (Schema::hasColumn('channel_master', 'missing_link')) {
                $insert['missing_link'] = '/listing-cjdropshipping';
            }
            DB::table('channel_master')->insert($insert);
        }

        if (! Schema::hasTable('channel_master_calculated_data')) {
            return;
        }

        $hasCalc = DB::table('channel_master_calculated_data')
            ->whereRaw("LOWER(REPLACE(REPLACE(REPLACE(TRIM(channel), ' ', ''), '-', ''), '_', '')) = ?", ['cjdropshipping'])
            ->exists();
        if ($hasCalc) {
            return;
        }

        $calc = [
            'channel' => 'CJdropshipping',
            'created_at' => $now,
            'updated_at' => $now,
        ];
        if (Schema::hasColumn('channel_master_calculated_data', 'type')) {
            $calc['type'] = 'Dropship';
        }
        if (Schema::hasColumn('channel_master_calculated_data', 'missing_link')) {
            $calc['missing_link'] = '/listing-cjdropshipping';
        }
        if (Schema::hasColumn('channel_master_calculated_data', 'calculated_at')) {
            $calc['calculated_at'] = $now;
        }
        DB::table('channel_master_calculated_data')->insert($calc);
    }

    public function down(): void
    {
        Schema::dropIfExists('cjdropshipping_listing_statuses');

        if (Schema::hasTable('channel_master')) {
            DB::table('channel_master')
                ->whereRaw("LOWER(REPLACE(REPLACE(REPLACE(TRIM(channel), ' ', ''), '-', ''), '_', '')) = ?", ['cjdropshipping'])
                ->update(['status' => 'Inactive', 'updated_at' => now()]);
        }
    }
};
