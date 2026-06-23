<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-designation Roles & Responsibilities templates.
 *
 * One row = one R&R bullet for a given designation. The first time a
 * designation is opened from the Task Summary R&R modal we ask AI to
 * seed the rows; from then on team members can add / delete / reorder
 * them manually, while users sharing the designation see the same list.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('designation_rr_items')) {
            return;
        }

        Schema::create('designation_rr_items', function (Blueprint $table) {
            $table->id();
            $table->string('designation', 191)->index();
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('source', 20)->default('manual'); // 'ai' on first seed, 'manual' afterwards
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['designation', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designation_rr_items');
    }
};
