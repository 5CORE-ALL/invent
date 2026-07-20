<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('escalated_claims_status_histories')) {
            return;
        }

        Schema::create('escalated_claims_status_histories', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date')->unique();
            $table->unsignedInteger('red_count')->default(0);
            $table->unsignedInteger('yellow_count')->default(0);
            $table->unsignedInteger('green_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalated_claims_status_histories');
    }
};
