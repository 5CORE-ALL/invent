<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per SKU per Reverb Bump % rule run (fill S Bump%, push to Reverb, or skipped by an exception).
     * Powers the change log / monitoring page (/reverb-bump-log).
     */
    public function up(): void
    {
        if (Schema::hasTable('reverb_bump_change_log')) {
            return;
        }

        Schema::create('reverb_bump_change_log', function (Blueprint $table) {
            $table->id();
            $table->string('run_id', 40)->index();
            $table->string('source', 12)->default('manual');   // manual | auto
            $table->string('action', 12);                       // fill | push | skip
            $table->string('status', 12);                       // ok | failed | skipped
            $table->string('sku', 191)->index();
            $table->string('reverb_listing_id', 64)->nullable();
            $table->unsignedInteger('sold')->default(0);        // RV L30 at the time
            $table->decimal('inv', 12, 2)->default(0);
            $table->decimal('price', 12, 2)->nullable();
            $table->unsignedBigInteger('views')->nullable();    // views at the time (baseline for monitoring)
            $table->string('old_s_bump', 20)->nullable();
            $table->string('old_live_bump', 20)->nullable();
            $table->string('new_bump', 20)->nullable();
            $table->string('reason', 191)->nullable();
            $table->text('message')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reverb_bump_change_log');
    }
};
