<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Stores user-editable ACOS → Suggested-Budget bands for the
 * /facebook-all-ads-sheet "Sbgt" column.
 *
 * Mirrors `ebay_sbid_rules`: unique `key` + JSON `rule`. Bands are
 * evaluated in ascending `acos_max` order, first match wins. Set
 * `acos_max = 9999` for the catch-all final band.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_sbgt_rules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();   // 'facebook_all'
            $table->json('rule');
            $table->timestamps();
        });

        // Seed the default rule = the brackets the user originally
        // requested so behaviour matches what they see on day one.
        DB::table('facebook_sbgt_rules')->insert([
            'key'        => 'facebook_all',
            'rule'       => json_encode([
                'bands' => [
                    ['acos_max' => 10,   'sbgt' => 20, 'label' => 'Excellent', 'color' => '#16a34a'],
                    ['acos_max' => 20,   'sbgt' => 15, 'label' => 'Good',      'color' => '#22c55e'],
                    ['acos_max' => 30,   'sbgt' => 10, 'label' => 'Fair',      'color' => '#facc15'],
                    ['acos_max' => 40,   'sbgt' => 5,  'label' => 'Poor',      'color' => '#f97316'],
                    ['acos_max' => 50,   'sbgt' => 2,  'label' => 'Bad',       'color' => '#ef4444'],
                    ['acos_max' => 9999, 'sbgt' => 1,  'label' => 'Critical',  'color' => '#7f1d1d'],
                ],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_sbgt_rules');
    }
};
