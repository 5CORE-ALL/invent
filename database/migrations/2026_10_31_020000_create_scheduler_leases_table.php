<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per scheduled job that must never run twice at once (see App\Support\SchedulerLease).
 * `owner_token` is NULL while nobody holds the lease; a holder that stops heart-beating loses it
 * when `expires_at` passes, and the next run takes it over.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scheduler_leases')) {
            return;
        }

        Schema::create('scheduler_leases', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('owner_token', 36)->nullable();
            $table->unsignedInteger('owner_pid')->nullable();
            $table->string('owner_host')->nullable();
            $table->timestamp('acquired_at')->nullable();
            $table->timestamp('heartbeat_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_finished_at')->nullable();
            $table->string('last_status', 32)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduler_leases');
    }
};
