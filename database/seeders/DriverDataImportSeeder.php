<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DriverDataImportSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('resources:import-driver-data', [
            '--fresh' => false,
        ]);

        $this->command?->line(Artisan::output());
    }
}
