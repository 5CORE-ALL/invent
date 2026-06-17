<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit log for Meta (Facebook) campaigns viewed on
 * /facebook-all-ads-sheet. Each row is one snapshot of "did this
 * campaign pass each checklist item at this point in time", along
 * with the auditor, timestamp and any free-form comment.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('facebook_campaign_audits')) {
            Schema::create('facebook_campaign_audits', function (Blueprint $table) {
                $table->id();
                $table->string('campaign_id', 64)->index();   // Meta campaign id (numeric string)
                $table->string('campaign_name')->nullable();  // captured at audit time for history readability
                // JSON map: { check_key: bool } — exactly the keys defined
                // in FacebookAllAdsSheetController::AUDIT_CHECKLIST. Stored
                // verbatim so we can render the same boxes back later.
                $table->json('checks');
                $table->unsignedTinyInteger('score_pct')->default(0); // 0-100, denormalised for cheap reads
                $table->text('comments')->nullable();
                $table->unsignedBigInteger('audited_by')->nullable()->index();
                $table->string('audited_by_name')->nullable();   // captured at audit time
                $table->timestamp('audited_at')->useCurrent();
                $table->timestamps();

                $table->index(['campaign_id', 'audited_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_campaign_audits');
    }
};
