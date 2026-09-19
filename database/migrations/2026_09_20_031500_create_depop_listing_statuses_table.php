<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('depop_listing_statuses')) {
            Schema::create('depop_listing_statuses', function (Blueprint $table) {
                $table->id();
                $table->string('sku')->index();
                $table->json('value');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('channel_master') && Schema::hasColumn('channel_master', 'listing_mode')) {
            DB::table('channel_master')
                ->whereRaw('LOWER(TRIM(channel)) = ?', ['depop'])
                ->where(function ($q) {
                    $q->whereNull('listing_mode')->orWhere('listing_mode', '');
                })
                ->update(['listing_mode' => 'CSV']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('depop_listing_statuses');
    }
};
