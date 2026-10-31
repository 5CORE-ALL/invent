<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Peer reviews shown on Team View.
 *
 * One row = one reviewer's 0-5 star rating of one reviewee for one factor
 * (sub-section) inside one section (personal / team / rr).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('team_reviews')) {
            return;
        }

        Schema::create('team_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reviewee_id');
            $table->unsignedBigInteger('reviewer_id');
            $table->string('section', 20);   // personal | team | rr
            $table->string('factor', 40);    // sub-section key, e.g. communication
            $table->unsignedTinyInteger('rating'); // 0..5
            $table->timestamps();

            $table->unique(['reviewee_id', 'reviewer_id', 'section', 'factor'], 'team_reviews_unique');
            $table->index(['reviewee_id', 'section']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_reviews');
    }
};
