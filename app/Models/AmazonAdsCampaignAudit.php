<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI audit of one Amazon campaign ("why is it not getting enough clicks?"), one row per channel + campaign.
 * Written by {@see \App\Services\AmazonAdsCampaignAuditService}; shown through the Audit column on /amazon-ads/all.
 */
class AmazonAdsCampaignAudit extends Model
{
    public const TABLE = 'amazon_ads_campaign_audits';

    public const STATUS_RUNNING = 'running';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    protected $table = self::TABLE;

    protected $guarded = [];

    protected $casts = [
        'report' => 'array',
        'facts' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /** The single definition of the table: the migration calls this too, so a missing table heals itself. */
    public static function ensureTable(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }
        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string('channel', 8); // sp | sb
            $table->string('campaign_id', 64);
            $table->string('campaign_name', 512)->nullable();
            $table->string('status', 16)->default(self::STATUS_RUNNING); // running | done | failed
            $table->string('severity', 16)->nullable(); // high | medium | low | ok
            $table->text('summary')->nullable();
            $table->longText('report')->nullable(); // AI report (JSON)
            $table->longText('facts')->nullable(); // the data the AI was given (JSON)
            $table->string('provider', 32)->nullable();
            $table->text('error')->nullable();
            $table->string('requested_by', 191)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['channel', 'campaign_id'], 'amz_ads_audit_campaign_unique');
            $table->index('status', 'amz_ads_audit_status_idx');
        });
    }
}
