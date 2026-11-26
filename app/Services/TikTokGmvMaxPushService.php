<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Queue of changes to push to TikTok GMV Max campaigns (currently: daily budget).
 *
 * TikTok blocks this server's IP, so the app never calls TikTok for writes. It records the
 * change here; the GitHub Actions job `tiktok-gmv-max-push.yml` (runs outside India) claims the
 * pending rows, applies them through the Marketing API and reports the result back.
 *
 * Every future automatic rule must go through queueBudget() so limits and the log apply to it too.
 */
class TikTokGmvMaxPushService
{
    public const TABLE = 'tiktok_gmv_max_push_queue';

    public const MAX_ATTEMPTS = 3;

    public static function ensureTable(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }
        Schema::create(self::TABLE, function ($t) {
            $t->id();
            $t->string('campaign_id', 40);
            $t->string('campaign_name')->nullable();
            $t->string('action', 20)->default('budget');
            $t->decimal('old_value', 14, 2)->nullable();
            $t->decimal('new_value', 14, 2);
            $t->string('status', 20)->default('pending'); // pending, processing, success, failed, cancelled
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->string('message', 300)->nullable();
            $t->string('source', 40)->default('manual');   // manual, rule:<name>
            $t->string('requested_by', 120)->nullable();
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'created_at']);
            $t->index('campaign_id');
        });
    }

    /**
     * Queue a daily-budget change for one campaign.
     *
     * @return array<string, mixed> the queue row
     *
     * @throws InvalidArgumentException when the change is refused (message is shown to the user)
     */
    public static function queueBudget(string $campaignId, float $budget, ?string $requestedBy = null, string $source = 'manual'): array
    {
        if (! config('services.tiktok_ads.push_enabled', true)) {
            throw new InvalidArgumentException('Pushing to TikTok is switched off (TIKTOK_GMV_MAX_PUSH_ENABLED=false).');
        }
        $campaignId = trim($campaignId);
        if (! preg_match('/^\d{5,25}$/', $campaignId)) {
            throw new InvalidArgumentException('Invalid campaign id.');
        }
        $budget = round($budget, 2);
        $min = (float) config('services.tiktok_ads.push_min_budget', 1);
        $max = (float) config('services.tiktok_ads.push_max_budget', 500);
        if ($budget < $min || $budget > $max) {
            throw new InvalidArgumentException(sprintf('Budget must be between %s and %s.', number_format($min, 2), number_format($max, 2)));
        }

        TikTokGmvMaxCampaignStore::ensureTable();
        $campaign = DB::table(TikTokGmvMaxCampaignStore::TABLE)
            ->where('campaign_id', $campaignId)
            ->orderByRaw("report_range = 'L30' desc")
            ->first();
        if (! $campaign) {
            throw new InvalidArgumentException('Unknown campaign (not in the synced GMV Max campaign list).');
        }
        $old = $campaign->budget !== null ? round((float) $campaign->budget, 2) : null;
        if ($old !== null && abs($old - $budget) < 0.005) {
            throw new InvalidArgumentException('Budget is already '.number_format($budget, 2).'.');
        }

        self::ensureTable();
        $now = now();
        $id = DB::transaction(function () use ($campaignId, $campaign, $old, $budget, $requestedBy, $source, $now) {
            // A newer request replaces an older one that was not applied yet.
            DB::table(self::TABLE)->where('campaign_id', $campaignId)->where('action', 'budget')
                ->where('status', 'pending')
                ->update(['status' => 'cancelled', 'message' => 'Replaced by a newer request', 'processed_at' => $now, 'updated_at' => $now]);

            return DB::table(self::TABLE)->insertGetId([
                'campaign_id' => $campaignId,
                'campaign_name' => $campaign->campaign_name,
                'action' => 'budget',
                'old_value' => $old,
                'new_value' => $budget,
                'status' => 'pending',
                'attempts' => 0,
                'source' => mb_substr($source, 0, 40),
                'requested_by' => $requestedBy !== null ? mb_substr($requestedBy, 0, 120) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        self::kickRelay();

        return (array) DB::table(self::TABLE)->where('id', $id)->first();
    }

    /**
     * Rows for the relay job. Rows stuck in "processing" for 15+ minutes go back to pending
     * (or fail after MAX_ATTEMPTS).
     *
     * @return list<array<string, mixed>>
     */
    public static function claimPending(int $limit = 20): array
    {
        self::ensureTable();
        $now = now();
        DB::table(self::TABLE)->where('status', 'processing')->where('updated_at', '<', $now->copy()->subMinutes(15))
            ->where('attempts', '>=', self::MAX_ATTEMPTS)
            ->update(['status' => 'failed', 'message' => 'No result from the relay job', 'processed_at' => $now, 'updated_at' => $now]);
        DB::table(self::TABLE)->where('status', 'processing')->where('updated_at', '<', $now->copy()->subMinutes(15))
            ->update(['status' => 'pending', 'updated_at' => $now]);

        $rows = DB::table(self::TABLE)->where('status', 'pending')->orderBy('id')->limit($limit)->get();
        $out = [];
        foreach ($rows as $r) {
            DB::table(self::TABLE)->where('id', $r->id)->update([
                'status' => 'processing',
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => $now,
            ]);
            $out[] = [
                'id' => (int) $r->id,
                'campaign_id' => (string) $r->campaign_id,
                'action' => (string) $r->action,
                'budget' => (float) $r->new_value,
            ];
        }

        return $out;
    }

    /**
     * Result reported by the relay job. $appliedBudget is the budget TikTok returned after the write.
     */
    public static function recordResult(int $id, bool $ok, ?string $message, ?float $appliedBudget = null): bool
    {
        self::ensureTable();
        $row = DB::table(self::TABLE)->where('id', $id)->first();
        if (! $row) {
            return false;
        }
        $now = now();
        DB::table(self::TABLE)->where('id', $id)->update([
            'status' => $ok ? 'success' : 'failed',
            'message' => $message !== null ? mb_substr($message, 0, 300) : null,
            'processed_at' => $now,
            'updated_at' => $now,
        ]);

        if ($ok) {
            $value = $appliedBudget ?? (float) $row->new_value;
            DB::table(TikTokGmvMaxCampaignStore::TABLE)
                ->where('campaign_id', $row->campaign_id)
                ->update(['budget' => round($value, 2)]);
        } else {
            Log::warning('TikTok GMV Max budget push failed', ['id' => $id, 'campaign_id' => $row->campaign_id, 'message' => $message]);
        }

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function recent(int $limit = 50): array
    {
        if (! Schema::hasTable(self::TABLE)) {
            return [];
        }

        return DB::table(self::TABLE)->orderByDesc('id')->limit($limit)->get()
            ->map(fn ($r) => (array) $r)->all();
    }

    /**
     * Optional: start the relay job right away instead of waiting for its 5-minute schedule.
     * Needs GITHUB_DISPATCH_TOKEN (fine-grained token with "Actions: write" on the repo).
     */
    private static function kickRelay(): void
    {
        $token = trim((string) config('services.tiktok_ads.github_dispatch_token'));
        if ($token === '') {
            return;
        }
        try {
            Http::withToken($token)->acceptJson()->timeout(8)
                ->post('https://api.github.com/repos/5CORE-ALL/invent/actions/workflows/tiktok-gmv-max-push.yml/dispatches', ['ref' => 'main']);
        } catch (\Throwable $e) {
            Log::info('TikTok push relay dispatch skipped: '.$e->getMessage());
        }
    }
}
