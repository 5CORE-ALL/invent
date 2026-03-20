<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ChannelMarginSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\ChannelMargin::insert([
            ['channel' => 'amazon', 'margin' => 0.67],
            ['channel' => 'ebay', 'margin' => 0.77],
            ['channel' => 'temu', 'margin' => 0.87],
            ['channel' => 'macy', 'margin' => 0.72],
        ]);
    }
}
