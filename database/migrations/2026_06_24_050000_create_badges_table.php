<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Curated pool of KPI badges that can be tagged on team members from the
 * Task Summary "KPI" column. New badges can be added at runtime by anyone
 * authorised (Director / Admin / Shobha).
 *
 *  icon  → Remix-Icon class name (e.g. "ri-star-line").
 *  color → hex code used for the chip background.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('badges')) {
            return;
        }

        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('icon', 60)->default('ri-medal-line');
            $table->string('color', 16)->default('#0d9488'); // teal default
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('name');
        });

        // Seed a small set of starter badges so the modal isn't blank on
        // first open. Operators can edit / extend these from the UI.
        $now = now();
        $rows = [
            ['name' => 'Star Performer',    'icon' => 'ri-star-line',          'color' => '#f59e0b', 'description' => 'Consistently exceeds targets.'],
            ['name' => 'On-Time Champion',  'icon' => 'ri-time-line',          'color' => '#0d9488', 'description' => 'Delivers tasks on or before the deadline.'],
            ['name' => 'Team Player',       'icon' => 'ri-team-line',          'color' => '#6366f1', 'description' => 'Helpful to colleagues and across functions.'],
            ['name' => 'Quick Learner',     'icon' => 'ri-flashlight-line',    'color' => '#06b6d4', 'description' => 'Picks up new skills and tools rapidly.'],
            ['name' => 'Mentor',            'icon' => 'ri-user-star-line',     'color' => '#8b5cf6', 'description' => 'Coaches and grows the people around them.'],
            ['name' => 'Quality Master',    'icon' => 'ri-shield-check-line',  'color' => '#15803d', 'description' => 'Low rework rate, consistently high quality.'],
            ['name' => 'Innovator',         'icon' => 'ri-lightbulb-line',     'color' => '#d946ef', 'description' => 'Brings fresh ideas and improvements.'],
            ['name' => 'Crisis Manager',    'icon' => 'ri-alarm-warning-line', 'color' => '#dc2626', 'description' => 'Stays calm and effective under fire.'],
        ];
        foreach ($rows as &$r) {
            $r['created_by'] = null;
            $r['created_at'] = $now;
            $r['updated_at'] = $now;
        }
        DB::table('badges')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('badges');
    }
};
