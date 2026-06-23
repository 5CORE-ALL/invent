<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot mapping managers to the juniors they oversee.
 *
 * Populated from the CL Mgr modal (a manager picks their juniors there).
 * Used to roll up juniors' scores into the manager's combined Mgr score.
 *
 * Kept as a separate table (rather than a manager_id column on users) so:
 *  - a junior can have multiple managers (e.g. matrixed teams),
 *  - we don't have to mutate the heavily-shared users schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('manager_juniors')) {
            return;
        }

        Schema::create('manager_juniors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manager_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('junior_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['manager_user_id', 'junior_user_id'], 'manager_juniors_pair_unique');
            $table->index('junior_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manager_juniors');
    }
};
