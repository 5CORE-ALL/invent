<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'President',
            'email' => 'president@5core.com',
            'password' => bcrypt('president@123'),
        ]);

        User::create([
            'name' => 'Ritu',
            'email' => 'ritu.kaur013@gmail.com',
            'password' => bcrypt('Ritu@123@'),
        ]);

        User::create([
            'name' => 'Jishan',
            'email' => 'support@5core.com',
            'password' => bcrypt('Jishan@123'),
        ]);
    }
}
