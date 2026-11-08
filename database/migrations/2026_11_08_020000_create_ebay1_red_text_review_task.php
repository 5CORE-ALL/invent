<?php

use App\Support\Ebay1RedTextReviewAutomatedTask;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Daily automated task for listing-mgr@5core.com: review the red text cells on /ebay1-data.
     * It is also ensured by tasks:generate-daily-automated, so it can never be missing.
     */
    public function up(): void
    {
        Ebay1RedTextReviewAutomatedTask::ensureTemplate();
    }

    public function down(): void
    {
        // The task belongs to the team once created; it is not removed on rollback.
    }
};
