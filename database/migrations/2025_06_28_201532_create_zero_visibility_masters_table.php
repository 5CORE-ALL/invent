<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('zero_visibility_masters', function (Blueprint $table) {
            $table->id();
            $table->string('channel_name');
            $table->text('sheet_link')->nullable();
            $table->boolean('is_ra_checked')->default(false);
            $table->integer('total_sku')->default(0);
            $table->integer('nr')->default(0);
            $table->integer('listed_req')->default(0);
            $table->integer('listed')->default(0);
            $table->integer('listing_pending')->default(0);
            $table->integer('zero_inv')->default(0);
            $table->integer('live_req')->default(0);
            $table->integer('active_and_live')->default(0);
            $table->integer('live_pending')->default(0);
            $table->integer('zero_visibility_sku_count')->default(0);
            $table->text('reason')->nullable();
            $table->text('step_taken')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zero_visibility_masters');
    }
};
