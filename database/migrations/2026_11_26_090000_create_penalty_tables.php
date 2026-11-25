<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Floating "Penalty" icon. Penalty points are added by president@5core.com (manually or by auto rules he
 * sets) and are visible to the user they belong to. Points of the last 30 days are shown live; on the 1st
 * of every month the previous month's total is frozen (penalty_months) and priced with the slab rates.
 * The purpose is to evaluate mistakes and shortcomings, not to deduct money.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('penalty_entries')) {
            Schema::create('penalty_entries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedSmallInteger('points');
                $table->text('reason');
                $table->string('source', 10)->default('manual'); // manual | auto
                $table->unsignedBigInteger('rule_id')->nullable();
                $table->date('penalty_date')->index();
                $table->string('period_key', 20)->nullable(); // dedupe key for auto rules (day / week / month)
                $table->string('created_by', 191)->nullable();
                $table->timestamps();
                $table->unique(['rule_id', 'user_id', 'period_key'], 'penalty_entries_rule_user_period_unique');
            });
        }

        if (! Schema::hasTable('penalty_rules')) {
            Schema::create('penalty_rules', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->boolean('is_active')->default(true);
                $table->string('metric', 40);
                $table->string('operator', 4)->default('>=');
                $table->decimal('threshold', 12, 2)->default(0);
                $table->unsignedSmallInteger('points')->default(1);
                $table->string('reason', 500);
                $table->string('repeat', 10)->default('daily'); // daily | weekly | monthly (one penalty per period)
                $table->string('run_time', 5)->default('19:00');
                $table->boolean('weekdays_only')->default(true);
                $table->timestamp('last_run_at')->nullable();
                $table->unsignedInteger('last_added_count')->default(0);
                $table->string('created_by', 191)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('penalty_slabs')) {
            Schema::create('penalty_slabs', function (Blueprint $table) {
                $table->id();
                $table->unsignedTinyInteger('position')->unique();
                $table->unsignedInteger('max_points')->nullable(); // null = no upper limit (last slab)
                $table->decimal('rate', 8, 2); // rupees per penalty point while the month total is in this slab
                $table->timestamps();
            });

            $now = now();
            foreach ([[1, 5, 1], [2, 10, 2], [3, 15, 3], [4, 20, 4], [5, null, 5]] as [$pos, $max, $rate]) {
                DB::table('penalty_slabs')->insert([
                    'position' => $pos, 'max_points' => $max, 'rate' => $rate, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        if (! Schema::hasTable('penalty_months')) {
            Schema::create('penalty_months', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->char('month', 7); // Y-m
                $table->unsignedInteger('points')->default(0);
                $table->decimal('rate', 8, 2)->default(0);
                $table->decimal('amount', 10, 2)->default(0);
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'month']);
            });
        }

        if (! Schema::hasTable('penalty_corrections')) {
            Schema::create('penalty_corrections', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('penalty_entry_id')->nullable()->index();
                $table->string('note', 500);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (['penalty_corrections', 'penalty_months', 'penalty_slabs', 'penalty_rules', 'penalty_entries'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
