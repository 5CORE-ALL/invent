<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_incentive_extend_requests')) {
            return;
        }

        Schema::create('user_incentive_extend_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_incentive_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('requested_by_user_id')->index();
            $table->string('current_cutoff', 32)->nullable();
            $table->date('requested_cutoff');
            $table->string('reason', 1000)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 1000)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_incentive_extend_requests');
    }
};
