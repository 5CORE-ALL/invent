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
use Illuminate\Support\Facades\DB;
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
                DB::raw('SUM(mid.impressions) as impressions'),
                DB::raw('SUM(mid.reach) as reach'),
                DB::raw('SUM(mid.clicks) as clicks'),
                DB::raw('SUM(CAST(mid.spend AS DECIMAL(15,2))) as spend'),
                DB::raw('SUM(mid.purchases) as purchases'),
                DB::raw('AVG(mid.frequency) as frequency')
            )
            ->groupBy('ma.id', 'ma.name')
            ->get();

        // Collect video action metrics (thruplay + avg watch time) from actions JSON
        $actionsRows = DB::table('meta_insights_daily as mid')
            ->join('meta_ads as ma', function ($j) {
                $j->on('ma.id', '=', 'mid.entity_id')->where('mid.entity_type', 'ad');
            })
            ->whereBetween('mid.date_start', [$startDate, $endDate])
            ->whereNotNull('mid.actions')
            ->select('ma.name as ad_name', 'mid.actions')
            ->get();

        $videoMetrics = [];
        foreach ($actionsRows as $row) {
            $actions = is_string($row->actions) ? json_decode($row->actions, true) : $row->actions;
            if (!is_array($actions)) continue;
            $name = $row->ad_name;
            if (!isset($videoMetrics[$name])) {
                $videoMetrics[$name] = ['thruplay' => 0, 'watch_total' => 0, 'watch_count' => 0];
            }
            foreach ($actions as $action) {
                $type = $action['action_type'] ?? '';
                $val  = (float)($action['value'] ?? 0);
                if ($type === 'video_thruplay_watched') {
                    $videoMetrics[$name]['thruplay'] += (int)$val;
                } elseif ($type === 'video_avg_time_watched') {
                    $videoMetrics[$name]['watch_total'] += $val;
                    $videoMetrics[$name]['watch_count']++;
                }
            }
        }

        $result = [];
        foreach ($videos as $video) {
            $topic   = mb_strtolower(trim($video->ads_topic_story));
            if (!$topic) { $result[$video->id] = null; continue; }

            $matched = $insightRows->filter(fn($r) => str_contains(mb_strtolower($r->ad_name), $topic));

            if ($matched->isEmpty()) { $result[$video->id] = null; continue; }

            $totalImpr      = (int) $matched->sum('impressions');
            $totalClicks    = (int) $matched->sum('clicks');
            $totalSpend     = (float) $matched->sum('spend');
            $totalPurchases = (int) $matched->sum('purchases');
            $totalReach     = (int) $matched->sum('reach');

            $thruplay    = 0;
            $watchTotal  = 0;
            $watchCount  = 0;
            foreach ($matched as $m) {
                $vm = $videoMetrics[$m->ad_name] ?? null;
                if ($vm) {
                    $thruplay   += $vm['thruplay'];
                    $watchTotal += $vm['watch_total'];
                    $watchCount += $vm['watch_count'];
                }
            }

            $result[$video->id] = [
                'ad_name'    => $matched->pluck('ad_name')->unique()->take(2)->implode(' / '),
                'ad_count'   => $matched->count(),
                'impressions'=> $totalImpr,
                'reach'      => $totalReach,
                'clicks'     => $totalClicks,
                'spend'      => round($totalSpend, 2),
                'ctr'        => $totalImpr > 0 ? round($totalClicks / $totalImpr * 100, 2) : 0,
                'cpm'        => $totalImpr > 0 ? round($totalSpend / $totalImpr * 1000, 2) : 0,
                'frequency'  => $matched->count() > 0 ? round((float)$matched->avg('frequency'), 2) : 0,
                'video_views'=> $thruplay,
                'results'    => $totalPurchases,
                'cost_result'=> $totalPurchases > 0 ? round($totalSpend / $totalPurchases, 2) : 0,
                'watch_time' => $watchCount > 0 ? round($watchTotal / $watchCount, 1) : 0,
            ];
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
