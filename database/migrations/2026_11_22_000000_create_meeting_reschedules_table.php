<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('meeting_reschedules')) {
            return;
        }

        Schema::create('meeting_reschedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meeting_id')->index();
            $table->unsignedBigInteger('requested_by')->index();
            // The slot the meeting had when the request was made / the new slot being asked for.
            $table->dateTime('old_start')->nullable();
            $table->dateTime('old_end')->nullable();
            $table->dateTime('new_start');
            $table->dateTime('new_end');
            $table->string('reason', 500)->nullable();
            // pending (waiting for the host) | approved (meeting moved) | declined
            $table->string('status', 16)->default('pending')->index();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_reschedules');
    }
};
