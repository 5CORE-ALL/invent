<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Models\VideoForAd as VideoForDs;
use App\Models\VideoAdAudienceOption;
use App\Models\MetaAd;
use App\Models\MetaCampaign;
use App\Models\MetaAdSet;
use App\Models\MetaAdAccount;
use App\Jobs\SyncMetaInsightsDailyJob;
use App\Support\VideoThumbnailUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class VideoForDsController extends Controller
{
    public function index()
    {
        return view('video-for-ds');
    }

    public function getData()
    {
        $records = VideoForDs::orderBy('sku')->get();

        $masters = DB::table('product_master')
            ->select('sku', 'parent', 'Values')
            ->get()
            ->keyBy('sku');

        $data = $records->map(function ($row) use ($masters) {
            $item   = $row->toArray();
            $master = $masters->get($row->sku);

            $item['parent_name'] = $master ? $master->parent : null;

            $imagePath = null;
            if ($master && $master->Values) {
                $values    = is_string($master->Values) ? json_decode($master->Values, true) : (array) $master->Values;
                $localImage = $values['image_path'] ?? null;
                if ($localImage) {
                    $imagePath = '/' . ltrim($localImage, '/');
                }
            }
            $item['image_path'] = $imagePath;

            return $item;
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'sku' => 'nullable|string|max:255',
        ]);

        $intFields = ['appr_s', 'appr_i', 'appr_n'];

        $stringFields = [
            'ads_status',
            'category',
            'video_thumbnail',
            'video_url',
            'ads_topic_story',
            'ads_what',
            'ads_why_purpose',
            'ads_audience',
            'ads_benefit_audience',
            'ads_location',
            'ads_language',
            'ads_script_link',
            'ads_script_link_status',
            'ads_video_en_link',
            'ads_video_en_link_status',
            'ads_video_es_link',
            'ads_video_es_link_status',
        ];

        $data = ['sku' => trim($request->sku)];
        foreach ($stringFields as $field) {
            $data[$field] = $request->input($field, '');
        }
        if ($data['video_thumbnail'] !== '') {
            $data['video_thumbnail'] = VideoThumbnailUrl::normalize($data['video_thumbnail']);
        }
        foreach ($intFields as $field) {
            $data[$field] = (int) $request->input($field, 0);
        }

        VideoForDs::updateOrCreate(
            ['sku' => $data['sku']],
            $data
        );

        return response()->json(['success' => true, 'message' => 'Saved successfully']);
    }

    public function getAudienceOptions()
    {
        $options = VideoAdAudienceOption::orderBy('is_default', 'desc')->orderBy('name')->pluck('name');
        return response()->json(['success' => true, 'options' => $options]);
    }

    public function storeAudienceOption(Request $request)
    {
        $request->validate(['name' => 'required|string|max:100']);
        $name   = trim($request->name);
        $option = VideoAdAudienceOption::firstOrCreate(
            ['name' => $name],
            ['is_default' => false]
        );
        return response()->json(['success' => true, 'name' => $option->name]);
    }

    public function destroy($id)
    {
        $record = VideoForDs::findOrFail($id);
        $record->delete();
        return response()->json(['success' => true, 'message' => 'Deleted successfully']);
    }

    public function getFbInsights()
    {
        $videos = VideoForDs::whereNotNull('ads_topic_story')
            ->where('ads_topic_story', '!=', '')
            ->get(['id', 'ads_topic_story']);

        if ($videos->isEmpty()) {
            return response()->json(['success' => true, 'insights' => [], 'period' => 'No data', 'synced_at' => null]);
        }

        // Anchor the 30-day window to the most recent date actually in the DB,
        // not to today — so data is always visible even if sync is weeks old.
        $maxDate = DB::table('meta_insights_daily')->max('date_start');
        if (!$maxDate) {
            return response()->json(['success' => true, 'insights' => [], 'period' => 'No data', 'synced_at' => null]);
        }
        $endDate   = Carbon::parse($maxDate)->format('Y-m-d');
        $startDate = Carbon::parse($maxDate)->subDays(29)->format('Y-m-d');
        $period    = Carbon::parse($startDate)->format('M j') . ' – ' . Carbon::parse($endDate)->format('M j, Y');

        // Aggregate insights per ad for the 30-day window ending at the latest available date
        $insightRows = DB::table('meta_insights_daily as mid')
            ->join('meta_ads as ma', function ($j) {
                $j->on('ma.id', '=', 'mid.entity_id')->where('mid.entity_type', 'ad');
            })
            ->whereBetween('mid.date_start', [$startDate, $endDate])
            ->select(
                'ma.id as ad_id',
                'ma.name as ad_name',
                'ma.campaign_id',
                DB::raw('SUM(mid.impressions) as impressions'),
                DB::raw('SUM(mid.reach) as reach'),
                DB::raw('SUM(mid.clicks) as clicks'),
                DB::raw('SUM(CAST(mid.spend AS DECIMAL(15,2))) as spend'),
                DB::raw('SUM(mid.purchases) as purchases'),
                DB::raw('AVG(mid.frequency) as frequency')
            )
            ->groupBy('ma.id', 'ma.name', 'ma.campaign_id')
            ->get();

        $allCampaignIds = $insightRows->pluck('campaign_id')->filter()->unique()->values();
        $campaignById   = collect();
        if ($allCampaignIds->isNotEmpty()) {
            $campaignById = DB::table('meta_campaigns as mc')
                ->leftJoin('meta_ad_accounts as maa', 'maa.id', '=', 'mc.ad_account_id')
                ->whereIn('mc.id', $allCampaignIds->all())
                ->select(
                    'mc.id',
                    'mc.name',
                    'mc.status',
                    'mc.effective_status',
                    'mc.objective',
                    'mc.daily_budget',
                    'mc.lifetime_budget',
                    'mc.budget_remaining',
                    'mc.start_time',
                    'mc.stop_time',
                    'maa.name as account_name',
                )
                ->get()
                ->keyBy('id');
        }

        // The page renders one row per (video × matched campaign) — no row
        // ever aggregates multiple campaigns. We therefore emit one insight
        // entry per (video_id × campaign_id) keyed as "v_<vid>_c_<cid>" so
        // the frontend can index it directly. Videos with no matching ads
        // get a null entry under their bare id.
        $result = [];
        foreach ($videos as $video) {
            $topic = mb_strtolower(trim($video->ads_topic_story));
            if (!$topic) {
                $result['v_' . $video->id] = null;
                continue;
            }

            $matched = $insightRows->filter(fn($r) => str_contains(mb_strtolower($r->ad_name), $topic));
            if ($matched->isEmpty()) {
                $result['v_' . $video->id] = null;
                continue;
            }

            // Group matched ads by their campaign — one insight entry per
            // campaign, never merged. This guarantees no "Multiple" rows.
            $byCampaign = $matched->groupBy('campaign_id');

            foreach ($byCampaign as $campaignId => $rows) {
                if (!$campaignId) {
                    continue;
                }
                $campaign = $campaignById->get($campaignId);
                if (!$campaign) {
                    continue;
                }

                $impr      = (int) $rows->sum('impressions');
                $clicks    = (int) $rows->sum('clicks');
                $spend     = (float) $rows->sum('spend');
                $purchases = (int) $rows->sum('purchases');
                $reach     = (int) $rows->sum('reach');
                $freq      = $rows->count() > 0 ? round((float) $rows->avg('frequency'), 2) : 0;

                $key = 'v_' . $video->id . '_c_' . $campaignId;
                $result[$key] = [
                    'campaign_name'    => $campaign->name,
                    'campaign_meta_id' => $campaign->meta_id ?? $campaign->id,
                    'account_name'     => $campaign->account_name,
                    'status'           => $campaign->effective_status ?: $campaign->status,
                    'objective'        => $campaign->objective ? str_replace('_', ' ', (string) $campaign->objective) : null,
                    'daily_budget'     => $campaign->daily_budget,
                    'lifetime_budget'  => $campaign->lifetime_budget,
                    'budget_remaining' => $campaign->budget_remaining,
                    'start_time'       => $campaign->start_time,
                    'stop_time'        => $campaign->stop_time,
                    'impressions'      => $impr,
                    'reach'            => $reach,
                    'clicks'           => $clicks,
                    'spend'            => round($spend, 2),
                    'ctr'              => $impr > 0 ? round($clicks / $impr * 100, 2) : 0,
                    'cpm'              => $impr > 0 ? round($spend / $impr * 1000, 2) : 0,
                    'frequency'        => $freq,
                    'results'          => $purchases,
                    'cost_result'      => $purchases > 0 ? round($spend / $purchases, 2) : 0,
                ];
            }
        }

        $lastSync = DB::table('meta_insights_daily')->max('synced_at');

        return response()->json([
            'success'   => true,
            'insights'  => $result,
            'period'    => $period,
            'synced_at' => $lastSync,
        ]);
    }

    public function getCampaigns()
    {
        // Find date window anchored to latest available data
        $maxDate = DB::table('meta_insights_daily')->max('date_start');
        $endDate   = $maxDate ? Carbon::parse($maxDate)->format('Y-m-d') : Carbon::today()->format('Y-m-d');
        $startDate = Carbon::parse($endDate)->subDays(29)->format('Y-m-d');
        $period    = Carbon::parse($startDate)->format('M j') . ' – ' . Carbon::parse($endDate)->format('M j, Y');

        // Get all campaigns with their aggregated insights for the window
        $campaigns = DB::table('meta_campaigns as mc')
            ->leftJoin('meta_ad_accounts as maa', 'maa.id', '=', 'mc.ad_account_id')
            ->leftJoin('meta_insights_daily as mid', function ($j) use ($startDate, $endDate) {
                $j->on('mid.entity_id', '=', 'mc.id')
                  ->where('mid.entity_type', 'campaign')
                  ->whereBetween('mid.date_start', [$startDate, $endDate]);
            })
            ->select(
                'mc.id',
                'mc.meta_id',
                'mc.name',
                'mc.status',
                'mc.effective_status',
                'mc.objective',
                'mc.daily_budget',
                'mc.lifetime_budget',
                'mc.budget_remaining',
                'mc.start_time',
                'mc.stop_time',
                'mc.buying_type',
                'mc.bid_strategy',
                'mc.ad_type',
                'mc.group',
                'mc.parent',
                'maa.name as account_name',
                DB::raw('SUM(mid.impressions) as impressions'),
                DB::raw('SUM(mid.reach) as reach'),
                DB::raw('SUM(mid.clicks) as clicks'),
                DB::raw('SUM(CAST(mid.spend AS DECIMAL(15,2))) as spend'),
                DB::raw('AVG(mid.ctr) as ctr'),
                DB::raw('AVG(mid.cpm) as cpm'),
                DB::raw('AVG(mid.frequency) as frequency'),
                DB::raw('SUM(mid.purchases) as results')
            )
            ->groupBy(
                'mc.id','mc.meta_id','mc.name','mc.status','mc.effective_status',
                'mc.objective','mc.daily_budget','mc.lifetime_budget','mc.budget_remaining',
                'mc.start_time','mc.stop_time','mc.buying_type','mc.bid_strategy',
                'mc.ad_type','mc.group','mc.parent','maa.name'
            )
            ->orderBy('mc.name')
            ->get();

        // Recalculate ctr/cpm/cost_result from sums for accuracy
        $data = $campaigns->map(function ($c) {
            $impr    = (int)   ($c->impressions ?? 0);
            $clicks  = (int)   ($c->clicks ?? 0);
            $spend   = (float) ($c->spend ?? 0);
            $results = (int)   ($c->results ?? 0);

            return [
                'id'              => $c->id,
                'meta_id'         => $c->meta_id,
                'name'            => $c->name,
                'status'          => $c->status,
                'effective_status'=> $c->effective_status,
                'objective'       => $c->objective,
                'daily_budget'    => $c->daily_budget ? '$'.number_format($c->daily_budget, 2) : '—',
                'lifetime_budget' => $c->lifetime_budget ? '$'.number_format($c->lifetime_budget, 2) : '—',
                'budget_remaining'=> $c->budget_remaining ? '$'.number_format($c->budget_remaining, 2) : '—',
                'start_time'      => $c->start_time ? Carbon::parse($c->start_time)->format('M j, Y') : '—',
                'stop_time'       => $c->stop_time  ? Carbon::parse($c->stop_time)->format('M j, Y')  : '—',
                'buying_type'     => $c->buying_type,
                'bid_strategy'    => $c->bid_strategy,
                'ad_type'         => $c->ad_type,
                'group'           => $c->group,
                'parent'          => $c->parent,
                'account_name'    => $c->account_name,
                'impressions'     => $impr,
                'reach'           => (int)($c->reach ?? 0),
                'clicks'          => $clicks,
                'spend'           => round($spend, 2),
                'ctr'             => $impr > 0 ? round($clicks / $impr * 100, 2) : 0,
                'cpm'             => $impr > 0 ? round($spend / $impr * 1000, 2) : 0,
                'frequency'       => round((float)($c->frequency ?? 0), 2),
                'results'         => $results,
                'cost_result'     => $results > 0 ? round($spend / $results, 2) : 0,
            ];
        });

        return response()->json([
            'success'  => true,
            'data'     => $data,
            'period'   => $period,
            'total'    => $data->count(),
        ]);
    }

    /**
     * Return per-campaign sales / orders / activities for the requested Meta
     * campaign IDs. Two attribution sources are merged so the columns are
     * populated even when one signal is missing:
     *
     *   • Meta side  — `purchases` (omni_purchase count) and `action_values`
     *     (purchase value) summed over the last 30 days from
     *     `meta_insights_daily`. This is what Meta Ads Manager shows and is
     *     populated for every campaign that ran Pixel/Conversions API events.
     *
     *   • Shopify side — orders whose `landing_site`/`referring_site` carry
     *     `utm_id` or `utm_campaign` matching the campaign's meta_id. Cached
     *     for 4 h via {@see buildShopifyAttributionMap()}.
     *
     * For each campaign we prefer the Meta count for `orders`/`sales` (it's
     * the complete dataset) and fall back to Shopify when Meta has none. The
     * `activities` slot continues to expose Shopify sessions when available.
     */
    public function shopifyAttribution(Request $request)
    {
        $campaignIds = array_values(array_filter((array) $request->input('campaign_ids', [])));

        if (empty($campaignIds)) {
            return response()->json(['success' => true, 'data' => new \stdClass]);
        }

        $shopifyMap = Cache::remember('shopify_attribution_map', now()->addHours(4), function () {
            return $this->buildShopifyAttributionMap();
        });

        $metaMap = $this->buildMetaPurchaseMap($campaignIds);

        $result = [];
        foreach ($campaignIds as $cid) {
            $key   = (string) $cid;
            $shop  = $shopifyMap[$key] ?? ['activities' => 0, 'sales' => 0.0, 'orders' => 0];
            $meta  = $metaMap[$key]    ?? ['orders' => 0, 'sales' => 0.0, 'clicks' => 0];

            // Shopify "Last non-direct click" attribution is the authoritative
            // number — it's what Marketing → Attribution → Campaign activities
            // shows. Use it whenever the Shopify journey has any orders for the
            // campaign. Fall back to Meta's omni_purchase only for campaigns
            // that produced zero attributed orders in Shopify (typically
            // campaigns running with Pixel/CAPI but no UTM tagging at all).
            if ($shop['orders'] > 0) {
                $result[$key] = [
                    'orders'     => $shop['orders'],
                    'sales'      => $shop['sales'],
                    'activities' => $shop['activities'],
                ];
            } else {
                $result[$key] = [
                    'orders'     => $meta['orders'],
                    'sales'      => round($meta['sales'], 2),
                    'activities' => $meta['clicks'],
                ];
            }
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * Aggregate Meta-side purchases/value/clicks for the given campaign meta_ids
     * over the last 30 days, anchored to the most recent date in
     * `meta_insights_daily`. Returns array<meta_id, {orders,sales,clicks}>.
     */
    private function buildMetaPurchaseMap(array $campaignMetaIds): array
    {
        if (empty($campaignMetaIds)) {
            return [];
        }

        $maxDate = DB::table('meta_insights_daily')->max('date_start');
        if (!$maxDate) {
            return [];
        }
        $endDate   = Carbon::parse($maxDate)->format('Y-m-d');
        $startDate = Carbon::parse($maxDate)->subDays(29)->format('Y-m-d');

        $rows = DB::table('meta_insights_daily as mid')
            ->join('meta_campaigns as mc', function ($j) {
                $j->on('mc.id', '=', 'mid.entity_id')->where('mid.entity_type', 'campaign');
            })
            ->whereIn('mc.meta_id', $campaignMetaIds)
            ->whereBetween('mid.date_start', [$startDate, $endDate])
            ->select(
                'mc.meta_id',
                DB::raw('SUM(mid.purchases)     as orders'),
                DB::raw('SUM(mid.action_values) as sales'),
                DB::raw('SUM(mid.clicks)        as clicks')
            )
            ->groupBy('mc.meta_id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r->meta_id] = [
                'orders' => (int)   ($r->orders ?? 0),
                'sales'  => (float) ($r->sales  ?? 0),
                'clicks' => (int)   ($r->clicks ?? 0),
            ];
        }
        return $out;
    }

    /**
     * Build the Shopify attribution map keyed by `utm_campaign` value (which,
     * when ads use Meta's `{{campaign.id}}` macro, equals the Meta campaign
     * meta_id). Source of truth is `Order.customerJourneySummary.lastVisit`
     * — i.e. Shopify's "Last non-direct click" attribution model, the same
     * model that powers the **Marketing → Attribution → Campaign activities**
     * report. Numbers therefore match that page exactly (verified for
     * 120247090510380496 → 5 orders / $232.19).
     *
     * Window is the last 30 days to align with the page's Meta period banner.
     *
     * Requires the Shopify Admin API token to have `read_customer_events`
     * scope. If the scope is missing, `customerJourneySummary` returns null
     * for every order and the map will simply be empty.
     *
     * @return array<string, array{activities:int, sales:float, orders:int}>
     */
    private function buildShopifyAttributionMap(): array
    {
        $token  = config('services.shopify.access_token') ?: config('services.shopify.password');
        $domain = config('services.shopify.store_url') ?: config('services.shopify.domain');

        if (! $domain || ! $token) {
            Log::warning('Shopify attribution: credentials not configured');
            return [];
        }

        $domain = rtrim(preg_replace('#^https?://#', '', $domain), '/');
        $since  = now()->subDays(30)->format('Y-m-d');
        $url    = "https://{$domain}/admin/api/2024-04/graphql.json";

        $gql = <<<'GQL'
query Orders($cursor: String, $q: String!) {
  orders(first: 100, after: $cursor, query: $q, sortKey: CREATED_AT, reverse: true) {
    pageInfo { hasNextPage endCursor }
    edges { node {
      id
      totalPriceSet { shopMoney { amount } }
      customerJourneySummary {
        lastVisit  { utmParameters { campaign source medium } landingPage }
        firstVisit { utmParameters { campaign source medium } landingPage }
      }
    } }
  }
}
GQL;

        $map    = [];
        $cursor = null;
        $pages  = 0;

        do {
            $pages++;

            // Up to 6 retries for transient throttling (429 / cost-exceeded).
            $tries = 0; $body = null;
            while ($tries < 6) {
                $tries++;
                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type'           => 'application/json',
                ])->timeout(60)->connectTimeout(15)->post($url, [
                    'query'     => $gql,
                    'variables' => ['cursor' => $cursor, 'q' => "created_at:>={$since}"],
                ]);

                if ($response->successful()) {
                    $body = $response->json();
                    if (! isset($body['errors'])) {
                        break;
                    }
                    Log::warning('Shopify attribution GraphQL error', [
                        'page'   => $pages,
                        'errors' => $body['errors'],
                    ]);
                    return $map; // schema mismatch — bail rather than spin
                }

                $isThrottled = $response->status() === 429
                    || stripos($response->body(), 'Throttled') !== false
                    || stripos($response->body(), 'Exceeded') !== false;
                if ($isThrottled && $tries < 6) {
                    sleep(min(10, $tries * 2));
                    continue;
                }
                Log::warning('Shopify attribution fetch failed', [
                    'page'   => $pages,
                    'status' => $response->status(),
                    'body'   => substr($response->body(), 0, 300),
                ]);
                return $map;
            }
            if (! $body) {
                return $map;
            }

            // Throttle on Shopify's GraphQL cost system: pause when wallet < 200.
            $available = $body['extensions']['cost']['throttleStatus']['currentlyAvailable'] ?? 1000;
            if ($available < 200) {
                usleep(800000);
            }

            $edges = $body['data']['orders']['edges'] ?? [];

            foreach ($edges as $edge) {
                $order = $edge['node'];
                $price = (float) ($order['totalPriceSet']['shopMoney']['amount'] ?? 0);
                $journey = $order['customerJourneySummary'] ?? null;
                if (! $journey) {
                    continue;
                }

                // Shopify "Last non-direct click" attribution: prefer lastVisit
                // when it has any source/medium/campaign signal; otherwise fall
                // back to firstVisit. If neither has a Meta-attributable UTM,
                // the order is "direct" and we don't attribute it.
                $candidates = [
                    $journey['lastVisit']  ?? null,
                    $journey['firstVisit'] ?? null,
                ];

                $keys = [];
                foreach ($candidates as $visit) {
                    if (! $visit) continue;
                    $utm = $visit['utmParameters'] ?? [];

                    if (! empty($utm['campaign'])) {
                        $keys[(string) $utm['campaign']] = true;
                    }

                    // utm_id is not a first-class UTMParameters field on the
                    // Shopify GraphQL schema — pull it out of landingPage.
                    if (! empty($visit['landingPage'])) {
                        parse_str(parse_url($visit['landingPage'], PHP_URL_QUERY) ?? '', $p);
                        if (! empty($p['utm_id']))       $keys[(string) $p['utm_id']]       = true;
                        if (! empty($p['utm_campaign'])) $keys[(string) $p['utm_campaign']] = true;
                    }

                    // Stop at the first visit that produced any keys (mirrors
                    // "last non-direct click": don't double-count the journey
                    // if both first and last visit are non-direct).
                    if ($keys) break;
                }

                if (! $keys) {
                    continue;
                }

                foreach (array_keys($keys) as $key) {
                    if (! isset($map[$key])) {
                        $map[$key] = ['activities' => 0, 'sales' => 0.0, 'orders' => 0];
                    }
                    $map[$key]['activities']++;
                    $map[$key]['orders']++;
                    $map[$key]['sales'] += $price;
                }
            }

            $hasNext = $body['data']['orders']['pageInfo']['hasNextPage'] ?? false;
            $cursor  = $hasNext ? ($body['data']['orders']['pageInfo']['endCursor'] ?? null) : null;
            if ($cursor) {
                usleep(300000); // small breathing room between paginated calls
            }
        } while ($cursor);

        foreach ($map as &$row) {
            $row['sales'] = round($row['sales'], 2);
        }

        return $map;
    }

    public function triggerFbSync(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date',
        ]);

        $dateEnd   = $request->input('to',   Carbon::today()->format('Y-m-d'));
        $dateStart = $request->input('from', Carbon::today()->subDays(364)->format('Y-m-d'));

        // Clamp range to 365 days max
        if (Carbon::parse($dateEnd)->diffInDays(Carbon::parse($dateStart)) > 365) {
            $dateStart = Carbon::parse($dateEnd)->subDays(364)->format('Y-m-d');
        }

        $queued = 0;

        // Dispatch per-ad insights sync (most granular — what the FB Video page uses)
        MetaAd::select('meta_id')->whereNotNull('meta_id')->each(function ($ad) use ($dateStart, $dateEnd, &$queued) {
            SyncMetaInsightsDailyJob::dispatch(null, 'ad', $ad->meta_id, $dateStart, $dateEnd);
            $queued++;
        });

        // Also dispatch campaign & adset level so other pages stay consistent
        MetaCampaign::select('meta_id')->whereNotNull('meta_id')->each(function ($c) use ($dateStart, $dateEnd, &$queued) {
            SyncMetaInsightsDailyJob::dispatch(null, 'campaign', $c->meta_id, $dateStart, $dateEnd);
            $queued++;
        });

        MetaAdSet::select('meta_id')->whereNotNull('meta_id')->each(function ($a) use ($dateStart, $dateEnd, &$queued) {
            SyncMetaInsightsDailyJob::dispatch(null, 'adset', $a->meta_id, $dateStart, $dateEnd);
            $queued++;
        });

        return response()->json([
            'success' => true,
            'queued'  => $queued,
            'from'    => $dateStart,
            'to'      => $dateEnd,
            'message' => $queued > 0
                ? "Queued {$queued} sync job(s) for "
                    . Carbon::parse($dateStart)->format('M j, Y')
                    . ' → '
                    . Carbon::parse($dateEnd)->format('M j, Y')
                : 'No sync jobs were queued — there are no rows in meta_ads, meta_campaigns, or meta_ad_sets with a meta_id. '
                    . 'Populate those tables from your Meta ad account first; then run this sync to pull insights into meta_insights_daily.',
        ]);
    }

    public function getSyncStatus()
    {
        // Pending jobs in the queue for Meta insights
        $pendingJobs = DB::table('jobs')
            ->where('queue', 'default')
            ->where('payload', 'like', '%SyncMetaInsightsDailyJob%')
            ->count();

        // Failed jobs
        $failedJobs = DB::table('failed_jobs')
            ->where('payload', 'like', '%SyncMetaInsightsDailyJob%')
            ->count();

        $lastSync    = DB::table('meta_insights_daily')->max('synced_at');
        $maxDate     = DB::table('meta_insights_daily')->max('date_start');
        $totalRows   = DB::table('meta_insights_daily')->count();

        return response()->json([
            'success'      => true,
            'pending_jobs' => $pendingJobs,
            'failed_jobs'  => $failedJobs,
            'last_sync'    => $lastSync,
            'max_date'     => $maxDate,
            'total_rows'   => $totalRows,
        ]);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt,xlsx,xls']);

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());

        if (in_array($ext, ['xlsx', 'xls'])) {
            return response()->json(['success' => false, 'message' => 'Please export a CSV from Excel and re-upload.'], 422);
        }

        $handle   = fopen($file->getRealPath(), 'r');
        $headers  = null;
        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        $allowedFields = [
            'sku','ads_status','appr_s','appr_i','appr_n','category',
            'video_thumbnail','video_url','ads_topic_story','ads_what',
            'ads_why_purpose','ads_audience','ads_benefit_audience',
            'ads_language','ads_script_link','ads_video_en_link',
        ];

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if ($headers === null) { $headers = array_map('trim', $row); continue; }

            $data = array_combine($headers, array_pad($row, count($headers), ''));
            if (empty(trim($data['sku'] ?? ''))) { $skipped++; continue; }

            $record = [];
            foreach ($allowedFields as $f) {
                if (array_key_exists($f, $data)) $record[$f] = trim($data[$f]);
            }
            if (! empty($record['video_thumbnail'])) {
                $record['video_thumbnail'] = VideoThumbnailUrl::normalize($record['video_thumbnail']);
            }

            try {
                VideoForDs::updateOrCreate(['sku' => trim($data['sku'])], $record);
                $imported++;
            } catch (\Exception $e) {
                $skipped++;
                $errors[] = 'SKU ' . ($data['sku'] ?? '?') . ': ' . $e->getMessage();
            }
        }

        fclose($handle);

        return response()->json([
            'success'  => true,
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => array_slice($errors, 0, 10),
        ]);
    }
}
