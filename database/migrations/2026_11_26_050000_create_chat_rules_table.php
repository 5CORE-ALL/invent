<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rules a director sets on /tasks/summary. A rule is "column  operator  number" (for example
 * Overdue >= 3) plus a message; matching team members get the message from the 5 Core Bot.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_rules')) {
            return;
        }

        Schema::create('chat_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->string('metric', 40);
            $table->string('operator', 4)->default('>=');
            $table->decimal('threshold', 12, 2)->default(0);
            $table->string('audience', 10)->default('all'); // all | users
            $table->json('user_ids')->nullable();
            $table->text('message');
            $table->string('send_time', 5)->default('10:00');
            $table->boolean('weekdays_only')->default(true);
            $table->string('frequency', 10)->default('daily'); // daily | weekly
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedInteger('last_sent_count')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('is_active', 'chat_rules_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_rules');
    }
};
