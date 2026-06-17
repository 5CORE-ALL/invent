<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ebay_sbid_rules')) {
            Schema::create('ebay_sbid_rules', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();   // e.g. 'ebay1', 'ebay2', 'ebay3'
                $table->json('rule');              // full rule JSON
                $table->timestamps();
            });
        }

        // Insert default rule (matches current hardcoded values)
        DB::table('ebay_sbid_rules')->updateOrInsert(
            ['key' => 'ebay1'],
            [
                'rule'       => json_encode([
                'bands' => [
                    ['scvr_max' => 4,    'bid' => 9.1,  'label' => 'Red',    'color' => '#dc3545'],
                    ['scvr_max' => 7,    'bid' => 7.1,  'label' => 'Yellow', 'color' => '#ffc107'],
                    ['scvr_max' => 13,   'bid' => 4.1,  'label' => 'Green',  'color' => '#198754'],
                    ['scvr_max' => 9999, 'bid' => 2.1,  'label' => 'Pink',   'color' => '#e83e8c'],
                ]
            ]),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('ebay_sbid_rules');
    }
};
