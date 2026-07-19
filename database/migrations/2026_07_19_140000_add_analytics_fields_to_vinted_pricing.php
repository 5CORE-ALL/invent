<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vinted_pricing')) {
            Schema::table('vinted_pricing', function (Blueprint $table) {
                if (! Schema::hasColumn('vinted_pricing', 'nr_req')) {
                    $table->string('nr_req', 10)->default('REQ')->after('l30');
                }
                if (! Schema::hasColumn('vinted_pricing', 'buyer_link')) {
                    $table->string('buyer_link', 1000)->nullable()->after('nr_req');
                }
                if (! Schema::hasColumn('vinted_pricing', 'seller_link')) {
                    $table->string('seller_link', 1000)->nullable()->after('buyer_link');
                }
            });
        }

        if (Schema::hasTable('marketplace_percentages')) {
            $exists = DB::table('marketplace_percentages')
                ->whereRaw('LOWER(TRIM(marketplace)) = ?', ['vinted'])
                ->exists();
            if (! $exists) {
                $now = now();
                DB::table('marketplace_percentages')->insert([
                    'marketplace' => 'Vinted',
                    'percentage' => 87,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vinted_pricing')) {
            Schema::table('vinted_pricing', function (Blueprint $table) {
                foreach (['nr_req', 'buyer_link', 'seller_link'] as $col) {
                    if (Schema::hasColumn('vinted_pricing', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
