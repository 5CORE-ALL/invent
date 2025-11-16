<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Database\Seeders\UserSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        \App\Models\User::factory(1)->create([
            'name' => 'Velonic',
            'email' => 'test@test.com',
            'email_verified_at' => now(),
            'password' => bcrypt('test@123'),
            'remember_token' => Str::random(10),
            'role' => 'super admin', // Add this
        ]);

        // Call additional seeders
        $this->call([
            UserSeeder::class,
            PermissionSeeder::class, // Add this line
        ]);
    }
}