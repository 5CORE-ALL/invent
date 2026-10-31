<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promotions page storage:
 *  - promotion_columns: user-created dynamic columns (header name + order)
 *  - promotion_channel_data: per-channel "Notes and Remarks" + values for dynamic columns
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('promotion_columns')) {
            Schema::create('promotion_columns', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('promotion_channel_data')) {
            Schema::create('promotion_channel_data', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('channel_master_id')->unique();
                $table->text('notes_remarks')->nullable();
                $table->json('values')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_channel_data');
        Schema::dropIfExists('promotion_columns');
    }
};
