<?php

namespace App\Http\Controllers\Campaigns;

use App\Models\GoogleAdsCampaign;
use App\Models\GoogleYoutubeVideoAiAudit;
use App\Models\GoogleYoutubeVideoAudit;
use App\Services\GoogleAdsSbidService;
use App\Support\GoogleShoppingCampaignsRawRule;
use App\Support\GoogleYoutubeCampaignAttrs;
use App\Support\GoogleYoutubeCampaignSales;
use App\Support\GoogleYoutubePauseRule;
use App\Support\GoogleYoutubeVideoPause;
use App\Support\GoogleYoutubeVideoAiAudit as YoutubeVideoAiAudit;
use App\Support\GoogleYoutubeVideoAuditChecklist;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * YouTube variant of {@see GoogleShoppingCampaignsController} — same grid, controls, and rule storage,
 * but scoped to campaigns whose name ends with the suffix " YT" (e.g. "CAR AUDIO Curiosity Gap Hook YT").
 */
class GoogleYoutubeAdsCampaignsController extends GoogleShoppingCampaignsController
{
    /** @var array<string, object>|null */
    private ?array $lifetimeByCampaignId = null;

    /** @var array<string, array{filled:bool, pct:?int, fail:int}>|null */
    private ?array $videoAuditFilledByCampaignId = null;

    /** @var array<string, bool>|null */
    private ?array $videoAuditAiFilledByCampaignId = null;

    /** @var array<string, array{category:?string, audience:?string, landing:?string}>|null */
    private ?array $campaignAttrsByCampaignId = null;

    /**
     * Render the duplicated grid view tied to YouTube Ads routes.
     */
    public function index()
    {
        $queueUrl = url('/google/shopping/youtube-ads/pause-script/queue?token='.GoogleYoutubeVideoPause::token());
        $callbackUrl = url('/google/shopping/youtube-ads/pause-script/callback');

        return view('campaign.google-youtube-ads', [
            'googleShoppingRule' => GoogleShoppingCampaignsRawRule::resolvedRule(),
            'youtubePauseRule' => GoogleYoutubePauseRule::resolved(),
            'youtubeAttrOptions' => GoogleYoutubeCampaignAttrs::options(),
            'youtubePauseWatcherScript' => GoogleYoutubeVideoPause::watcherScript($queueUrl, $callbackUrl),
        ]);
    }

    public function getPauseRule(): JsonResponse
    {
        return response()->json([
            'rule' => GoogleYoutubePauseRule::resolved(),
        ]);
    }

    public function savePauseRule(Request $request): JsonResponse
    {
        try {
            $normalized = GoogleYoutubePauseRule::normalize($request->all());
            GoogleYoutubePauseRule::persist($normalized);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 422,
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Could not save Pause rule.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }

        return response()->json([
            'message' => 'Pause rule saved.',
            'rule' => GoogleYoutubePauseRule::resolved(),
            'status' => 200,
        ]);
    }

    public function pushPauseRule(Request $request): JsonResponse
    {
        $raw = $request->input('campaign_ids');
        $ids = [];
        if (is_array($raw)) {
            foreach ($raw as $id) {
                if (! is_scalar($id)) {
                    continue;
                }
                $d = preg_replace('/\D/', '', (string) $id);
                if ($d !== '' && strlen($d) <= 32) {
                    $ids[$d] = true;
                }
                if (count($ids) >= 1000) {
                    break;
                }
            }
        }
        $campaignIds = array_keys($ids);
        if ($campaignIds === []) {
            return response()->json([
                'ok' => false,
                'exit_code' => 1,
                'command' => 'push-pause-youtube',
                'message' => 'No campaign_ids to process. Load a page with data, or select rows with the checkboxes.',
                'output' => '',
            ], 422);
        }

        $rule = GoogleYoutubePauseRule::resolved();
        if (empty($rule['enabled'])) {
            return response()->json([
                'ok' => false,
                'exit_code' => 1,
                'command' => 'push-pause-youtube',
                'message' => 'Pause rule is disabled. Enable it in Pause Rule first.',
                'output' => '',
            ], 422);
        }

        $customerId = preg_replace('/\D/', '', (string) config('services.google_ads.login_customer_id'));
        if ($customerId === '') {
            return response()->json([
                'ok' => false,
                'exit_code' => 1,
                'command' => 'push-pause-youtube',
                'message' => 'Google Ads customer ID is not configured.',
                'output' => '',
            ], 500);
        }

        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '0');
        set_time_limit(0);

        /** @var GoogleAdsSbidService $sbidService */
        $sbidService = app(GoogleAdsSbidService::class);
        $rowsById = $this->enrichedRowsForCampaignIds($campaignIds);
        $channelById = GoogleYoutubeVideoPause::channelTypesByCampaignId($campaignIds);
        $lines = [];
        $pausedApi = 0;
        $queuedScript = [];
        $skipped = 0;
        $errors = 0;

        $lines[] = 'Pushing Pause Rule for '.count($campaignIds).' campaign id(s)...';
        $lines[] = 'ENABLED campaigns that match a Spend LT + ACOS LT slab are paused.';
        $lines[] = 'YouTube VIDEO campaigns cannot be paused through the Google Ads API (MUTATE_NOT_ALLOWED). Those are queued for a Google Ads Script.';

        foreach ($campaignIds as $campaignId) {
            $row = $rowsById[$campaignId] ?? null;
            if ($row === null) {
                $lines[] = "[SKIP] {$campaignId}: not found in grid data.";
                $skipped++;

                continue;
            }

            $name = (string) ($row['campaign_name'] ?? $campaignId);
            $status = strtoupper(trim((string) ($row['campaign_status'] ?? '')));
            $spendLt = (float) ($row['spend_lt'] ?? 0);
            $acosLt = (float) ($row['acos_lt'] ?? 0);

            if ($status === 'PAUSED') {
                $lines[] = "[SKIP] {$name} ({$campaignId}): already PAUSED in Google Ads.";
                $skipped++;

                continue;
            }
            if ($status !== 'ENABLED') {
                $lines[] = "[SKIP] {$name} ({$campaignId}): status is {$status}, not ENABLED.";
                $skipped++;

                continue;
            }
            if (! GoogleYoutubePauseRule::shouldPause($spendLt, $acosLt, $rule)) {
                $lines[] = "[SKIP] {$name} ({$campaignId}): Spend LT {$spendLt} / ACOS LT {$acosLt} does not match a slab.";
                $skipped++;

                continue;
            }

            $channel = $channelById[$campaignId] ?? '';
            if (GoogleYoutubeVideoPause::isVideoChannel($channel)) {
                $queuedScript[$campaignId] = $name;
                $lines[] = "[SCRIPT] {$name} ({$campaignId}): VIDEO — queued for Google Ads Script.";

                continue;
            }

            try {
                $campaignResourceName = "customers/{$customerId}/campaigns/{$campaignId}";
                $sbidService->pauseCampaign($customerId, $campaignResourceName);
                GoogleAdsCampaign::where('campaign_id', $campaignId)
                    ->update(['campaign_status' => 'PAUSED']);
                $lines[] = "[PAUSED] {$name} ({$campaignId}): Spend LT {$spendLt}, ACOS LT {$acosLt} — paused via API.";
                $pausedApi++;
            } catch (\Throwable $e) {
                if (GoogleYoutubeVideoPause::isVideoMutateBlocked($e)) {
                    $queuedScript[$campaignId] = $name;
                    $lines[] = "[SCRIPT] {$name} ({$campaignId}): API blocked VIDEO mutate — queued for script.";

                    continue;
                }
                $lines[] = "[ERROR] {$name} ({$campaignId}): ".$this->shortGoogleAdsError($e);
                $errors++;
            }
        }

        $script = '';
        $queued = count($queuedScript);
        if ($queuedScript !== []) {
            GoogleYoutubeVideoPause::enqueue($queuedScript);
            $script = GoogleYoutubeVideoPause::oneShotScript(
                array_keys($queuedScript),
                url('/google/shopping/youtube-ads/pause-script/callback')
            );
            GoogleYoutubeVideoPause::storeLastScript($script);
            $lines[] = '';
            $lines[] = 'NEXT STEP: copy the Google Ads Script shown below this log.';
            $lines[] = 'Google Ads → Tools → Bulk actions → Scripts → + → paste → Authorize → Run.';
            $lines[] = '';
            $lines[] = '----- BEGIN GOOGLE ADS SCRIPT -----';
            $lines[] = $script;
            $lines[] = '----- END GOOGLE ADS SCRIPT -----';
        }

        $lines[] = '';
        $lines[] = "Done. Paused via API: {$pausedApi}, Script queued: {$queued}, Skipped: {$skipped}, Errors: {$errors}.";

        if ($pausedApi > 0) {
            $this->bumpRawGridRowsCache();
        }

        $ok = $errors === 0;
        $message = $queued > 0
            ? "Pause push ready — {$queued} VIDEO campaign(s) need the Google Ads Script (API cannot pause VIDEO)."
            : ($ok
                ? "Pause push finished — {$pausedApi} campaign(s) paused in Google Ads."
                : "Pause push finished with {$errors} error(s).");

        return response()->json([
            'ok' => $ok,
            'exit_code' => $ok ? 0 : 1,
            'command' => 'push-pause-youtube',
            'message' => $message,
            'output' => implode("\n", $lines),
            'ads_script' => $script,
            'script_queued' => $queued,
            'paused_api' => $pausedApi,
        ], $ok ? 200 : 422);
    }

    public function latestPauseScript(): JsonResponse
    {
        $ids = GoogleYoutubeVideoPause::pendingIds();
        $script = GoogleYoutubeVideoPause::currentScript(
            url('/google/shopping/youtube-ads/pause-script/callback')
        );

        return response()->json([
            'ok' => $script !== '',
            'queued' => count($ids),
            'ads_script' => $script,
            'message' => $script === ''
                ? 'No VIDEO campaigns are queued. Click Push Pause first.'
                : (count($ids) > 0
                    ? count($ids).' VIDEO campaign(s) queued. Copy the script and Run it in Google Ads.'
                    : 'Showing the last generated Google Ads Script.'),
        ]);
    }

    public function pauseScriptQueue(Request $request): JsonResponse
    {
        if (! GoogleYoutubeVideoPause::tokenMatches(
            (string) $request->query('token', $request->input('token', ''))
        )) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json([
            'campaign_ids' => GoogleYoutubeVideoPause::pendingIds(),
        ]);
    }

    public function pauseScriptCallback(Request $request): JsonResponse
    {
        if (! GoogleYoutubeVideoPause::tokenMatches(
            (string) $request->input('token', $request->query('token', ''))
        )) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $raw = $request->input('paused_ids', []);
        $ids = [];
        if (is_array($raw)) {
            foreach ($raw as $id) {
                if (! is_scalar($id)) {
                    continue;
                }
                $d = preg_replace('/\D/', '', (string) $id);
                if ($d !== '') {
                    $ids[] = $d;
                }
            }
        }
        $marked = GoogleYoutubeVideoPause::markPaused($ids);
        if ($marked > 0) {
            $this->bumpRawGridRowsCache();
        }

        return response()->json([
            'ok' => true,
            'marked' => $marked,
        ]);
    }

    private function shortGoogleAdsError(\Throwable $e): string
    {
        $msg = trim($e->getMessage());
        if (preg_match('/"message"\s*:\s*"([^"]+)"/', $msg, $m)) {
            $inner = $m[1];
            if (str_contains($msg, 'MUTATE_NOT_ALLOWED')) {
                return $inner.' (VIDEO campaigns cannot be mutated via the Google Ads API)';
            }

            return $inner;
        }
        if (strlen($msg) > 240) {
            return substr($msg, 0, 237).'...';
        }

        return $msg;
    }

    public function saveCampaignAttr(Request $request): JsonResponse
    {
        try {
            $saved = GoogleYoutubeCampaignAttrs::saveValue(
                (string) $request->input('campaign_id', ''),
                (string) $request->input('field', ''),
                (string) $request->input('value', '')
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'category' => $saved['category'],
            'audience' => $saved['audience'],
            'landing' => $saved['landing'],
            'options' => $saved['options'],
        ]);
    }

    public function saveCampaignAttrOption(Request $request): JsonResponse
    {
        try {
            $saved = GoogleYoutubeCampaignAttrs::addOption(
                (string) $request->input('kind', ''),
                (string) $request->input('label', '')
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'kind' => $saved['kind'],
            'label' => $saved['label'],
            'options' => $saved['options'],
        ]);
    }

    public function getVideoAudit(Request $request): JsonResponse
    {
        $cid = trim((string) $request->input('campaign_id', ''));
        if ($cid === '') {
            return response()->json([
                'success' => false,
                'error' => 'Missing campaign_id.',
            ], 422);
        }

        $latest = null;
        $history = [];
        if (Schema::hasTable('google_youtube_video_audits')) {
            $latest = GoogleYoutubeVideoAudit::query()
                ->where('campaign_id', $cid)
                ->orderByDesc('id')
                ->first();
            $history = GoogleYoutubeVideoAudit::query()
                ->where('campaign_id', $cid)
                ->orderByDesc('id')
                ->limit(50)
                ->get(['id', 'fail_count', 'audited_at', 'audited_by_name', 'comments', 'checks'])
                ->map(static function (GoogleYoutubeVideoAudit $h) {
                    $checks = GoogleYoutubeVideoAuditChecklist::normalizeChecks(
                        is_array($h->checks) ? $h->checks : []
                    );
                    $tally = GoogleYoutubeVideoAuditChecklist::tally($checks);

                    return [
                        'id' => $h->id,
                        'fail_count' => (int) $h->fail_count,
                        'score_pct' => $tally['pct'],
                        'audited_at' => optional($h->audited_at)->format('Y-m-d H:i'),
                        'audited_by_name' => $h->audited_by_name,
                        'comments' => $h->comments,
                        'checks' => $checks,
                    ];
                })
                ->all();
        }

        return response()->json([
            'success' => true,
            'checklist' => GoogleYoutubeVideoAuditChecklist::items(),
            'latest' => $latest ? [
                'checks' => GoogleYoutubeVideoAuditChecklist::normalizeChecks(
                    is_array($latest->checks) ? $latest->checks : []
                ),
                'comments' => $latest->comments,
                'fail_count' => (int) $latest->fail_count,
                'score_pct' => GoogleYoutubeVideoAuditChecklist::tally(
                    GoogleYoutubeVideoAuditChecklist::normalizeChecks(
                        is_array($latest->checks) ? $latest->checks : []
                    )
                )['pct'],
                'audited_at' => optional($latest->audited_at)->format('Y-m-d H:i'),
                'audited_by_name' => $latest->audited_by_name,
            ] : null,
            'filled' => $latest !== null && GoogleYoutubeVideoAuditChecklist::isFilled(
                GoogleYoutubeVideoAuditChecklist::normalizeChecks(
                    is_array($latest->checks) ? $latest->checks : []
                ),
                $latest->comments
            ),
            'history' => $history,
        ]);
    }

    public function saveVideoAudit(Request $request): JsonResponse
    {
        if (! Schema::hasTable('google_youtube_video_audits')) {
            return response()->json([
                'success' => false,
                'error' => 'Table google_youtube_video_audits does not exist. Run migrations.',
            ], 500);
        }

        $cid = trim((string) $request->input('campaign_id', ''));
        $name = trim((string) $request->input('campaign_name', ''));
        $rawChecks = $request->input('checks', []);
        $comments = trim((string) $request->input('comments', ''));

        if ($cid === '') {
            return response()->json(['success' => false, 'error' => 'Missing campaign_id.'], 422);
        }
        if (! is_array($rawChecks)) {
            return response()->json(['success' => false, 'error' => 'checks must be an object.'], 422);
        }

        $checks = GoogleYoutubeVideoAuditChecklist::normalizeChecks($rawChecks);
        if (! GoogleYoutubeVideoAuditChecklist::isFilled($checks, $comments)) {
            return response()->json([
                'success' => false,
                'error' => 'Answer at least one checkpoint or add a comment.',
            ], 422);
        }

        $user = $request->user();
        $row = GoogleYoutubeVideoAudit::query()->create([
            'campaign_id' => $cid,
            'campaign_name' => $name !== '' ? $name : null,
            'checks' => $checks,
            'fail_count' => GoogleYoutubeVideoAuditChecklist::failCount($checks),
            'comments' => $comments !== '' ? $comments : null,
            'audited_by' => $user->id ?? null,
            'audited_by_name' => $user->name ?? null,
            'audited_at' => now(),
        ]);

        $this->videoAuditFilledByCampaignId = null;

        $tally = GoogleYoutubeVideoAuditChecklist::tally($checks);

        return response()->json([
            'success' => true,
            'filled' => true,
            'fail_count' => (int) $row->fail_count,
            'score_pct' => $tally['pct'],
            'audited_at' => optional($row->audited_at)->format('Y-m-d H:i'),
            'audited_by_name' => $row->audited_by_name,
        ]);
    }

    public function getVideoAiAudit(Request $request): JsonResponse
    {
        $cid = trim((string) $request->input('campaign_id', ''));
        $history = [];
        $latest = null;
        if ($cid !== '' && Schema::hasTable('google_youtube_video_ai_audits')) {
            $latest = GoogleYoutubeVideoAiAudit::query()
                ->where('campaign_id', $cid)
                ->orderByDesc('id')
                ->first();
            $history = GoogleYoutubeVideoAiAudit::query()
                ->where('campaign_id', $cid)
                ->orderByDesc('id')
                ->limit(30)
                ->get()
                ->map(static fn (GoogleYoutubeVideoAiAudit $h) => [
                    'id' => $h->id,
                    'fail_count' => (int) $h->fail_count,
                    'model' => $h->model,
                    'video_url' => $h->video_url,
                    'prompt_used' => $h->prompt_used,
                    'result' => is_array($h->result) ? $h->result : [],
                    'audited_at' => optional($h->audited_at)->format('Y-m-d H:i'),
                    'audited_by_name' => $h->audited_by_name,
                ])
                ->all();
        }

        return response()->json([
            'success' => true,
            'checklist' => GoogleYoutubeVideoAuditChecklist::items(),
            'prompt' => YoutubeVideoAiAudit::currentPrompt(),
            'default_prompt' => YoutubeVideoAiAudit::defaultPrompt(),
            'prompt_history' => YoutubeVideoAiAudit::promptHistory(),
            'latest' => $latest ? [
                'video_url' => $latest->video_url,
                'prompt_used' => $latest->prompt_used,
                'result' => is_array($latest->result) ? $latest->result : [],
                'fail_count' => (int) $latest->fail_count,
                'model' => $latest->model,
                'audited_at' => optional($latest->audited_at)->format('Y-m-d H:i'),
                'audited_by_name' => $latest->audited_by_name,
            ] : null,
            'filled' => $latest !== null && is_array($latest->result) && $latest->result !== [],
            'history' => $history,
        ]);
    }

    public function saveVideoAiPrompt(Request $request): JsonResponse
    {
        $prompt = trim((string) $request->input('prompt', ''));
        if ($prompt === '') {
            return response()->json(['success' => false, 'error' => 'Prompt cannot be empty.'], 422);
        }
        try {
            $user = $request->user();
            YoutubeVideoAiAudit::persistPrompt($prompt, $user->id ?? null, $user->name ?? null);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'prompt' => YoutubeVideoAiAudit::currentPrompt(),
            'prompt_history' => YoutubeVideoAiAudit::promptHistory(),
        ]);
    }

    public function runVideoAiAudit(Request $request): JsonResponse
    {
        if (! Schema::hasTable('google_youtube_video_ai_audits')) {
            return response()->json([
                'success' => false,
                'error' => 'Table google_youtube_video_ai_audits does not exist. Run migrations.',
            ], 500);
        }

        $cid = trim((string) $request->input('campaign_id', ''));
        $name = trim((string) $request->input('campaign_name', ''));
        $videoUrl = trim((string) $request->input('video_url', ''));
        $prompt = trim((string) $request->input('prompt', ''));
        if ($cid === '') {
            return response()->json(['success' => false, 'error' => 'Missing campaign_id.'], 422);
        }
        if ($prompt === '') {
            $prompt = YoutubeVideoAiAudit::currentPrompt();
        }

        $user = $request->user();
        try {
            YoutubeVideoAiAudit::persistPrompt($prompt, $user->id ?? null, $user->name ?? null);
            $out = YoutubeVideoAiAudit::analyze($prompt, $videoUrl, [
                'campaign_id' => $cid,
                'campaign_name' => $name,
                'spend_lt' => $request->input('spend_lt'),
                'sales_lt' => $request->input('sales_lt'),
                'sold_lt' => $request->input('sold_lt'),
                'acos_lt' => $request->input('acos_lt'),
                'views_lt' => $request->input('views_lt'),
                'spend' => $request->input('spend'),
                'ad_sales_L30' => $request->input('ad_sales_L30'),
                'acos_l30' => $request->input('acos_l30'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }

        $row = GoogleYoutubeVideoAiAudit::query()->create([
            'campaign_id' => $cid,
            'campaign_name' => $name !== '' ? $name : null,
            'video_url' => $videoUrl !== '' ? $videoUrl : null,
            'prompt_used' => $prompt,
            'result' => $out['result'],
            'fail_count' => $out['fail_count'],
            'model' => $out['model'],
            'audited_by' => $user->id ?? null,
            'audited_by_name' => $user->name ?? null,
            'audited_at' => now(),
        ]);
        $this->videoAuditAiFilledByCampaignId = null;

        return response()->json([
            'success' => true,
            'filled' => true,
            'result' => $out['result'],
            'fail_count' => $out['fail_count'],
            'model' => $out['model'],
            'prompt' => YoutubeVideoAiAudit::currentPrompt(),
            'prompt_history' => YoutubeVideoAiAudit::promptHistory(),
            'audited_at' => optional($row->audited_at)->format('Y-m-d H:i'),
            'audited_by_name' => $row->audited_by_name,
        ]);
    }

    /**
     * Restrict every raw-grid query to campaigns whose name ends with " YT".
     * Leading space ensures we match the word suffix and not substrings like "LYT".
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    protected function applyCampaignNameScope($query, string $columnExpression = 'campaign_name'): void
    {
        $query->whereRaw("UPPER({$columnExpression}) LIKE ?", ['% YT']);
    }

    /**
     * YouTube keeps real CPC (spend ÷ clicks) and adds CPS (spend ÷ sold).
     * CPV stays on `cpv_L30` (spend ÷ TrueView views) — do not overwrite CPC.
     *
     * @param  array<string, mixed>  $arr
     */
    protected function applyRowChannelOverrides(array &$arr): void
    {
        $spend = (float) ($arr['spend'] ?? 0);
        $clicks = (float) ($arr['metrics_clicks'] ?? 0);
        $arr['cpc_L30'] = $clicks > 0 ? round($spend / $clicks, 2) : 0.0;

        $name = (string) ($arr['campaign_name'] ?? '');
        $sold = (float) ($arr['ad_sold_L30'] ?? 0);
        // Transaction value (GA4 / Ads) first; Shopify price × sold only if that $ is missing.
        $sales = GoogleYoutubeCampaignSales::lift(
            (float) ($arr['ad_sales_L30'] ?? 0),
            $sold,
            $name
        );
        $arr['ad_sales_L30'] = $sales;
        $arr['cps_L30'] = $sold > 0 ? round($spend / $sold, 2) : 0.0;

        $price = GoogleYoutubeCampaignSales::priceForCampaign($name);
        if ($price !== null && empty($arr['price'])) {
            $arr['price'] = $price;
        }

        $spendR = (int) round((float) ($arr['spend'] ?? 0));
        $salesR = (int) round($sales);
        $acos = 0.0;
        if ($salesR >= 1) {
            $acos = ($spendR / $salesR) * 100.0;
        } elseif ($spendR > 0) {
            $acos = 100.0;
        }
        $arr['acos_l30'] = $acos;
        $arr['sbgt'] = GoogleShoppingCampaignsRawRule::sbgtFromAcos(
            $acos,
            GoogleShoppingCampaignsRawRule::resolvedRule()
        );

        $this->applyLifetimeColumns($arr, $name);

        $cid = (string) ($arr['campaign_id'] ?? '');
        $auditMeta = $cid !== '' ? ($this->videoAuditMetaMap()[$cid] ?? null) : null;
        $arr['video_audit_filled'] = ! empty($auditMeta['filled']);
        $arr['video_audit_pct'] = $auditMeta['pct'] ?? null;
        $arr['video_audit_ai_filled'] = $cid !== ''
            && ! empty($this->videoAuditAiFilledMap()[$cid]);

        $attrs = $cid !== '' ? ($this->campaignAttrsMap()[$cid] ?? []) : [];
        $arr['yt_category'] = (string) ($attrs['category'] ?? '');
        $arr['yt_audience'] = (string) ($attrs['audience'] ?? '');
        $arr['yt_landing'] = (string) ($attrs['landing'] ?? '');

        // UB% stays in enrich for SBID; do not show 7/2/1 UB% on this grid.
        unset(
            $arr['ub7'],
            $arr['ub2'],
            $arr['ub1'],
            $arr['l1_spend'],
            $arr['sbid'],
            $arr['id_alert_title'],
            $arr['id_mismatch']
        );
    }

    /**
     * Lifetime = every daily row for this campaign_id (not the L30 window).
     * Sales uses the same GA4 → Ads → Shopify-price fallback as L30.
     *
     * @param  array<string, mixed>  $arr
     */
    private function applyLifetimeColumns(array &$arr, string $campaignName): void
    {
        $lt = $this->lifetimeMetricsByCampaignId()[(string) ($arr['campaign_id'] ?? '')] ?? null;
        $spendLt = $lt ? (float) $lt->spend_lt : 0.0;
        $soldLt = $lt
            ? static::resolveSoldL30Value((float) $lt->actual_sold, (float) $lt->ads_sold)
            : 0.0;
        $salesLt = $lt
            ? GoogleYoutubeCampaignSales::lift(
                static::resolveSalesL30Value((float) $lt->actual, (float) $lt->ads),
                $soldLt,
                $campaignName !== '' ? $campaignName : (string) ($lt->campaign_name ?? '')
            )
            : 0.0;

        $clicksLt = $lt ? (float) $lt->clicks_lt : 0.0;
        $imprLt = $lt ? (float) $lt->impr_lt : 0.0;
        $viewsLt = $lt ? (int) $lt->views_lt : 0;
        $arr['views_lt'] = $viewsLt;
        $arr['clicks_lt'] = (int) $clicksLt;
        $arr['video_views_L30'] = (int) ($arr['video_views_L30'] ?? 0);
        $arr['spend_lt'] = $spendLt;
        $arr['sold_lt'] = $soldLt;
        $arr['sales_lt'] = $salesLt;
        $arr['cpc_lt'] = $clicksLt > 0 ? round($spendLt / $clicksLt, 2) : 0.0;
        $arr['cps_lt'] = $soldLt > 0 ? round($spendLt / $soldLt, 2) : 0.0;
        $arr['cpv_lt'] = $viewsLt > 0 ? round($spendLt / $viewsLt, 2) : 0.0;
        $arr['ctr_lt'] = $imprLt > 0 ? round(($clicksLt / $imprLt) * 100.0, 2) : 0.0;
        $arr['cvr_lt'] = $clicksLt > 0 ? round(($soldLt / $clicksLt) * 100.0, 1) : 0.0;

        $spendR = (int) round($spendLt);
        $salesR = (int) round($salesLt);
        $acosLt = 0.0;
        if ($salesR >= 1) {
            $acosLt = ($spendR / $salesR) * 100.0;
        } elseif ($spendR > 0) {
            $acosLt = 100.0;
        }
        $arr['acos_lt'] = $acosLt;
    }

    /**
     * @return array<string, object>
     */
    private function lifetimeMetricsByCampaignId(): array
    {
        if ($this->lifetimeByCampaignId !== null) {
            return $this->lifetimeByCampaignId;
        }

        $query = DB::table('google_ads_campaigns')
            ->whereNotNull('campaign_id')
            ->whereNotNull('date');
        $this->applyCampaignNameScope($query);

        $map = [];
        foreach (
            $query
                ->selectRaw('campaign_id')
                ->selectRaw('MAX(campaign_name) as campaign_name')
                ->selectRaw('COALESCE(SUM(metrics_cost_micros), 0) / 1000000.0 as spend_lt')
                ->selectRaw('COALESCE(SUM(metrics_clicks), 0) as clicks_lt')
                ->selectRaw('COALESCE(SUM(metrics_impressions), 0) as impr_lt')
                ->selectRaw('COALESCE(SUM(metrics_video_views), 0) as views_lt')
                ->selectRaw('COALESCE(SUM(ga4_actual_revenue), 0) as actual')
                ->selectRaw('COALESCE(SUM(ga4_ad_sales), 0) as ads')
                ->selectRaw('COALESCE(SUM(ga4_actual_sold_units), 0) as actual_sold')
                ->selectRaw('COALESCE(SUM(ga4_sold_units), 0) as ads_sold')
                ->groupBy('campaign_id')
                ->get() as $row
        ) {
            $map[(string) $row->campaign_id] = $row;
        }

        $this->lifetimeByCampaignId = $map;

        return $this->lifetimeByCampaignId;
    }

    /**
     * @return array<string, array{filled:bool, pct:?int, fail:int}>
     */
    private function videoAuditMetaMap(): array
    {
        if ($this->videoAuditFilledByCampaignId !== null) {
            return $this->videoAuditFilledByCampaignId;
        }

        $this->videoAuditFilledByCampaignId = GoogleYoutubeVideoAuditChecklist::latestMetaByCampaignId();

        return $this->videoAuditFilledByCampaignId;
    }

    /**
     * @return array<string, array{category:?string, audience:?string, landing:?string}>
     */
    private function campaignAttrsMap(): array
    {
        if ($this->campaignAttrsByCampaignId !== null) {
            return $this->campaignAttrsByCampaignId;
        }

        $this->campaignAttrsByCampaignId = GoogleYoutubeCampaignAttrs::mapByCampaignId();

        return $this->campaignAttrsByCampaignId;
    }

    /**
     * @return array<string, bool>
     */
    private function videoAuditAiFilledMap(): array
    {
        if ($this->videoAuditAiFilledByCampaignId !== null) {
            return $this->videoAuditAiFilledByCampaignId;
        }

        $this->videoAuditAiFilledByCampaignId = YoutubeVideoAiAudit::filledByCampaignId();

        return $this->videoAuditAiFilledByCampaignId;
    }

    /**
     * @param  array<string, mixed>  $arr
     */
    protected function adjustChannelRowSales(array $arr, float $sales): float
    {
        return GoogleYoutubeCampaignSales::lift(
            $sales,
            (float) ($arr['sold_l30_agg'] ?? $arr['ad_sold_L30'] ?? 0),
            (string) ($arr['campaign_name'] ?? '')
        );
    }

    /**
     * @param  list<string>  $campaignIds
     * @return array{spend: float, sales: float, clicks: float, sold: float, bgt: float}
     */
    protected function computeL30BadgeTotalsForDate(string $endYmd, array $campaignIds = []): array
    {
        $tot = parent::computeL30BadgeTotalsForDate($endYmd, $campaignIds);

        $end = Carbon::parse($endYmd)->startOfDay();
        $start = $end->copy()->subDays(29);
        $query = DB::table('google_ads_campaigns')
            ->whereNotNull('date')
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')]);
        $this->applyCampaignNameScope($query);
        if ($campaignIds !== []) {
            $query->whereIn('campaign_id', $campaignIds);
        }

        $rows = $query
            ->selectRaw('campaign_name')
            ->selectRaw('SUM(ga4_actual_revenue) as actual')
            ->selectRaw('SUM(ga4_ad_sales) as ads')
            ->selectRaw('SUM(ga4_actual_sold_units) as actual_sold')
            ->selectRaw('SUM(ga4_sold_units) as ads_sold')
            ->groupBy('campaign_name')
            ->get();

        $sales = 0.0;
        foreach ($rows as $r) {
            $sold = static::resolveSoldL30Value((float) ($r->actual_sold ?? 0), (float) ($r->ads_sold ?? 0));
            $base = static::resolveSalesL30Value((float) ($r->actual ?? 0), (float) ($r->ads ?? 0));
            $sales += GoogleYoutubeCampaignSales::lift($base, $sold, (string) ($r->campaign_name ?? ''));
        }
        $tot['sales'] = round($sales, 2);

        return $tot;
    }

    /**
     * Prefer GA4 actual revenue, else Google Ads conversionsValue (transaction
     * value after the conversion action is updated). Shopify price × sold is
     * applied later only when that $ is still $0 / the old $1 placeholder.
     */
    protected static function salesL30SqlExpression(): string
    {
        return 'CASE WHEN COALESCE(agg.sum_ga4_actual, 0) > 0 THEN COALESCE(agg.sum_ga4_actual, 0) ELSE COALESCE(agg.sum_ga4_ads, 0) END';
    }

    protected static function resolveSalesL30Value(float $sumGa4ActualRevenue, float $sumGoogleAdsConversionsValue): float
    {
        return $sumGa4ActualRevenue > 0 ? $sumGa4ActualRevenue : $sumGoogleAdsConversionsValue;
    }

    /**
     * Same fallback for Sold: GA4 actual purchases when present, otherwise
     * Google Ads `metrics.conversions` (`ga4_sold_units`).
     */
    protected static function soldL30SqlExpression(): string
    {
        return 'CASE WHEN COALESCE(agg.sum_ga4_actual_sold, 0) > 0 THEN COALESCE(agg.sum_ga4_actual_sold, 0) ELSE COALESCE(agg.sum_ga4_ads_sold, 0) END';
    }

    protected static function resolveSoldL30Value(float $sumGa4ActualSoldUnits, float $sumGoogleAdsConversions): float
    {
        return $sumGa4ActualSoldUnits > 0 ? $sumGa4ActualSoldUnits : $sumGoogleAdsConversions;
    }

    protected function channelKey(): string
    {
        return 'youtube';
    }

    protected function pushSbgtCommandLabel(): string
    {
        return 'push-sbgt-youtube';
    }

    protected function auditView(): string
    {
        return 'campaign.google-youtube-ads-audit';
    }

    protected function auditGridRouteName(): string
    {
        return 'google.youtube.ads.campaigns';
    }
}
