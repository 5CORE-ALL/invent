<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MetaAdGroup;

class MetaAdGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $groups = [
            'DRM THRN',
            'PIN BNCH',
            'DYNAMIC MICROPHONES',
            'MIC STAND',
            'FLOOR GUITAR STANDS',
            'SPEAKER STANDS',
            'MIC',
            'INSTRUMENT MICS',
            'WIRELESS MICS',
            'ALL STANDS',
            'STOOLS AND BENCHES',
            'GUITAR ACCESSORIES',
            'MIXERS',
            'ALL',
            'DS CH SDL',
            'KYBRD SND',
            'GITR SND',
        ];

        foreach ($groups as $groupName) {
            MetaAdGroup::firstOrCreate(
                ['group_name' => $groupName],
                ['group_name' => $groupName]
            );
        }
    }
}

