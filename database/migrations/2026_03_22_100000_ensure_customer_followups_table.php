<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates customer_followups if it was never created (e.g. 2026_03_20_100001 ran
 * early-return when channel_master was missing, but was still marked migrated).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_followups')) {
            return;
        }

        Schema::create('customer_followups', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_id')->unique();
            $table->string('order_id')->nullable();
            $table->unsignedBigInteger('channel_master_id')->nullable();
            $table->string('customer_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('issue_type', 32);
            $table->string('status', 32);
            $table->string('priority', 32);
            $table->date('followup_date');
            $table->time('followup_time')->nullable();
            $table->dateTime('next_followup_at')->nullable();
            $table->string('assigned_executive')->nullable();
            $table->text('comments')->nullable();
            $table->text('internal_remarks')->nullable();
            $table->string('reference_link', 512)->nullable();
            $table->timestamps();
            $table->index(['status', 'priority']);
            $table->index('next_followup_at');
        });

        if (Schema::hasTable('channel_master')) {
            Schema::table('customer_followups', function (Blueprint $table) {
                $table->foreign('channel_master_id')
                    ->references('id')
                    ->on('channel_master')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_followups');
    }
};
