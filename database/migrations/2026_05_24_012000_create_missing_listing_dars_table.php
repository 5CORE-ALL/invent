<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * missing_listing_dars — Daily Activity Report submissions made from the
     * Missing Listing page. Each row is a single DAR submission tied to the
     * user who submitted it, with a free-form report body and a submission
     * timestamp used to drive the History badge / modal.
     *
     * Kept separate from the existing daily_activity_reports table because
     * that table is channel-scoped with a structured JSON responsibilities
     * payload and a (user, channel, date) uniqueness constraint that would
     * block multiple submissions per day from this page.
     */
    public function up(): void
    {
        if (Schema::hasTable('missing_listing_dars')) {
            return;
        }

        Schema::create('missing_listing_dars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('report');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index('submitted_at');
            $table->index(['user_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missing_listing_dars');
    }
};
