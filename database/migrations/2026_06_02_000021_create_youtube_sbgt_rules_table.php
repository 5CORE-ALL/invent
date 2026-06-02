<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * User-editable ACOS → Suggested-Budget bands for the YouTube Video Ads
 * "Sbgt" column. Unique `key` + JSON `rule`. Bands evaluated ascending
 * by `acos_max`, first match wins.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('youtube_sbgt_rules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();   // 'youtube_all'
            $table->json('rule');
            $table->timestamps();
        });

        DB::table('youtube_sbgt_rules')->insert([
            'key'        => 'youtube_all',
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
        Schema::dropIfExists('youtube_sbgt_rules');
    }
};
