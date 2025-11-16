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
        Schema::create('listing_audit_masters', function (Blueprint $table) {
            $table->id();
            $table->string('channel');
            $table->string('link')->nullable();
            $table->boolean('is_ra_checked')->default(false);
            $table->integer('not_listed')->default(0);
            $table->integer('not_live')->default(0);
            $table->integer('category_issue')->default(0);
            $table->integer('attr_not_filled')->default(0);
            $table->integer('a_plus_issue')->default(0);
            $table->integer('video_issue')->default(0);
            $table->integer('title_issue')->default(0);
            $table->integer('images')->default(0);
            $table->integer('description')->default(0);
            $table->integer('bullet_points')->default(0);
            $table->integer('in_variation')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listing_audit_masters');
    }
};
