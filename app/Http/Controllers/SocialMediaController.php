<?php

namespace App\Http\Controllers;

use App\Jobs\SyncSocialMediaAccount;
use App\Models\SocialMediaAccount;
use App\Models\SocialMediaKpiTarget;
use App\Models\SocialMediaPost;
use App\Models\SocialMediaSyncLog;
use App\Models\User;
use App\SocialMedia\SocialMediaKpiService;
use App\SocialMedia\SocialMediaPlatformRegistry;
use App\SocialMedia\SocialMediaReportService;
use App\SocialMedia\SocialMetric;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SocialMediaController extends Controller
{
    public function __construct(
        private readonly SocialMediaReportService $reports,
        private readonly SocialMediaPlatformRegistry $platforms,
        private readonly SocialMediaKpiService $kpi,
    ) {
    }

    public function dashboard(Request $request)
    {
        $this->authorize('social_media.view');
        [$start, $end, $filters] = $this->period($request);
        $report = $this->reports->dashboard($start, $end, $filters);

        return view('social-media.dashboard', $this->pageData($request, $start, $end, $filters, $report));
    }

    public function analytics(Request $request)
    {
        $this->authorize('social_media.analytics');
        [$start, $end, $filters] = $this->period($request);
        $report = $this->reports->dashboard($start, $end, $filters);

        return view('social-media.analytics', $this->pageData($request, $start, $end, $filters, $report));
    }

    public function executive(Request $request)
    {
        $this->authorize('social_media.analytics');
        [$start, $end, $filters] = $this->period($request);
        $report = $this->reports->dashboard($start, $end, $filters);

        return view('social-media.executive', $this->pageData($request, $start, $end, $filters, $report));
    }

    public function accounts()
    {
        $this->authorize('social_media.view');
        $accounts = SocialMediaAccount::query()
            ->with('user:id,name')
            ->orderBy('platform')
            ->orderBy('account_name')
            ->get();
        $latest = $this->latestAccountFigures($accounts->pluck('id'));

        return view('social-media.accounts', [
            'accounts' => $accounts,
            'latest' => $latest,
            'platforms' => $this->platforms->platforms(),
            'users' => $this->executives(),
            'catalog' => config('social_media.metrics'),
        ]);
    }

    public function storeAccount(Request $request)
    {
        $this->authorize('social_media.connect');
        $data = $this->validateAccount($request, true);
        $account = SocialMediaAccount::query()->updateOrCreate(
            [
                'platform' => $data['platform'],
                'external_account_id' => $data['external_account_id'],
            ],
            $this->accountAttributes($request, $data, true)
        );

        return redirect()->route('social-media.accounts')->with('status', $account->account_name.' connected.');
    }

    public function updateAccount(Request $request, SocialMediaAccount $account)
    {
        $this->authorize('social_media.manage');
        $data = $this->validateAccount($request, false);
        $account->fill($this->accountAttributes($request, $data, false));
        if ($request->filled('access_token')) {
            $account->access_token = $request->string('access_token')->toString();
            $account->status = 'connected';
            $account->last_sync_error = null;
        }
        if ($request->filled('refresh_token')) {
            $account->refresh_token = $request->string('refresh_token')->toString();
        }
        $account->save();

        return redirect()->route('social-media.accounts')->with('status', $account->account_name.' updated.');
    }

    public function destroyAccount(SocialMediaAccount $account)
    {
        $this->authorize('social_media.manage');
        $name = $account->account_name;
        $account->delete();

        return redirect()->route('social-media.accounts')->with('status', $name.' removed.');
    }

    public function syncAccount(SocialMediaAccount $account)
    {
        $this->authorize('social_media.sync');
        if ($account->tokenStatus() === 'expired' || $account->tokenStatus() === 'missing') {
            return back()->with('error', ucfirst($account->platform).' access token expired — reconnect the account.');
        }
        SyncSocialMediaAccount::dispatch($account->id);

        return back()->with('status', 'Sync queued for '.$account->account_name.'.');
    }

    public function content(Request $request)
    {
        $this->authorize('social_media.analytics');
        [$start, $end, $filters] = $this->period($request);
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $sortable = ['published_at', 'content_type', 'title'];
        if (! in_array($sort, $sortable, true)) {
            $sort = 'published_at';
        }

        $posts = SocialMediaPost::query()
            ->with(['account:id,platform,account_name', 'latestMetric'])
            ->whereHas('account', function ($query) use ($filters) {
                $query->when(! empty($filters['platform']), fn ($q) => $q->where('platform', $filters['platform']))
                    ->when(! empty($filters['account_id']), fn ($q) => $q->whereKey($filters['account_id']))
                    ->when(! empty($filters['user_id']), fn ($q) => $q->where('user_id', $filters['user_id']));
            })
            ->when($filters['content_type'] ?? null, fn ($q, $type) => $q->where('content_type', $type))
            ->whereBetween('published_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->string('q')->toString().'%'))
            ->orderBy($sort, $direction)
            ->paginate(25)
            ->withQueryString();

        return view('social-media.content', array_merge(
            $this->pageData($request, $start, $end, $filters, []),
            ['posts' => $posts, 'kpi' => $this->kpi]
        ));
    }

    public function showContent(SocialMediaPost $post)
    {
        $this->authorize('social_media.analytics');
        $post->load(['account:id,platform,account_name,profile_url', 'metrics' => fn ($q) => $q->orderBy('metric_date')]);

        return view('social-media.content-show', [
            'post' => $post,
            'kpi' => $this->kpi,
        ]);
    }

    public function targets()
    {
        $this->authorize('social_media.targets');

        return view('social-media.targets', [
            'targets' => SocialMediaKpiTarget::query()->with('user:id,name')->orderByDesc('period_start')->paginate(30),
            'metrics' => ['posts', 'reach', 'impressions', 'engagement', 'engagement_rate', 'follower_growth', 'video_views', 'website_clicks', 'leads'],
            'platforms' => $this->platforms->platforms(),
            'users' => $this->executives(),
        ]);
    }

    public function storeTarget(Request $request)
    {
        $this->authorize('social_media.targets');
        $data = $request->validate([
            'metric_name' => ['required', 'string', 'max:64'],
            'target_value' => ['required', 'numeric'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'platform' => ['nullable', Rule::in($this->platforms->platforms())],
            'user_id' => ['nullable', 'exists:users,id'],
        ]);
        SocialMediaKpiTarget::query()->create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('social-media.targets')->with('status', 'Target saved.');
    }

    public function destroyTarget(SocialMediaKpiTarget $target)
    {
        $this->authorize('social_media.targets');
        $target->delete();

        return redirect()->route('social-media.targets')->with('status', 'Target removed.');
    }

    public function reports(Request $request)
    {
        $this->authorize('social_media.reports');
        [$start, $end, $filters] = $this->period($request);

        return view('social-media.reports', $this->pageData($request, $start, $end, $filters, []));
    }

    public function exportReport(Request $request): StreamedResponse
    {
        $this->authorize('social_media.reports');
        [$start, $end, $filters] = $this->period($request);
        $report = $this->reports->build($start, $end, $filters);
        $filename = 'social-media-kpi-'.$start->toDateString().'-'.$end->toDateString().'.csv';

        return response()->streamDownload(function () use ($report, $start, $end) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Social Media KPI report', $start->toDateString(), $end->toDateString()]);
            fputcsv($out, ['KPI', 'Actual', 'Availability', 'Target', 'Achievement %', 'Status', 'Previous period change %']);
            foreach ($report['cards'] as $key => $card) {
                if (! is_array($card) || ! isset($card['label'])) {
                    continue;
                }
                $progress = $report['progress'][$key] ?? [];
                fputcsv($out, [
                    $card['label'],
                    $card['availability'] === SocialMetric::AVAILABLE ? $card['value'] : 'NOT_AVAILABLE',
                    $card['availability'],
                    $progress['target'] ?? '',
                    $progress['percent'] ?? '',
                    $progress['status'] ?? '',
                    ($card['previous_period']['availability'] ?? '') === SocialMetric::AVAILABLE
                        ? ($card['previous_period']['value'] ?? '')
                        : 'NOT_AVAILABLE',
                ]);
            }
            fputcsv($out, []);
            fputcsv($out, ['Platform', 'Reach', 'Engagement', 'Followers', 'Posts']);
            foreach ($report['platforms'] as $row) {
                fputcsv($out, [
                    $row['platform'],
                    $row['reach'] ?? 'NOT_AVAILABLE',
                    $row['engagement'] ?? 'NOT_AVAILABLE',
                    $row['followers'] ?? 'NOT_AVAILABLE',
                    $row['posts'],
                ]);
            }
            fputcsv($out, []);
            foreach ($report['notes'] as $note) {
                fputcsv($out, ['Note', $note]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function health()
    {
        $this->authorize('social_media.view');
        $logs = SocialMediaSyncLog::query()
            ->with('account:id,platform,account_name')
            ->orderByDesc('started_at')
            ->paginate(40);

        return view('social-media.health', [
            'logs' => $logs,
            'accounts' => SocialMediaAccount::query()->orderBy('account_name')->get(),
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: array<string, mixed>}
     */
    private function period(Request $request): array
    {
        $preset = $request->string('preset')->toString();
        $end = $request->filled('end') ? Carbon::parse($request->string('end')->toString()) : now();
        $start = $request->filled('start') ? Carbon::parse($request->string('start')->toString()) : $end->copy()->startOfMonth();
        if ($preset === 'daily') {
            $start = $end->copy()->startOfDay();
        } elseif ($preset === 'weekly') {
            $start = $end->copy()->startOfWeek();
        } elseif ($preset === 'monthly') {
            $start = $end->copy()->startOfMonth();
        }
        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        return [$start->startOfDay(), $end->endOfDay(), [
            'platform' => $request->string('platform')->toString() ?: null,
            'account_id' => $request->integer('account_id') ?: null,
            'user_id' => $request->integer('user_id') ?: null,
            'content_type' => $request->string('content_type')->toString() ?: null,
        ]];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function pageData(Request $request, Carbon $start, Carbon $end, array $filters, array $report): array
    {
        return [
            'report' => $report,
            'start' => $start,
            'end' => $end,
            'filters' => $filters,
            'platforms' => $this->platforms->platforms(),
            'accounts' => SocialMediaAccount::query()->orderBy('account_name')->get(['id', 'platform', 'account_name']),
            'users' => $this->executives(),
            'preset' => $request->string('preset')->toString(),
        ];
    }

    private function executives()
    {
        return User::query()
            ->where('email', 'like', '%@5core.com')
            ->orderBy('name')
            ->get(['id', 'name', 'designation']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAccount(Request $request, bool $creating): array
    {
        return $request->validate([
            'platform' => ['required', Rule::in($this->platforms->platforms())],
            'external_account_id' => ['required', 'string', 'max:191'],
            'account_name' => ['required', 'string', 'max:191'],
            'username' => ['nullable', 'string', 'max:191'],
            'profile_url' => ['nullable', 'url', 'max:500'],
            'access_token' => [$creating ? 'required' : 'nullable', 'string'],
            'refresh_token' => ['nullable', 'string'],
            'token_expires_at' => ['nullable', 'date'],
            'user_id' => ['nullable', 'exists:users,id'],
            'sync_enabled' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function accountAttributes(Request $request, array $data, bool $creating): array
    {
        $attributes = [
            'platform' => $data['platform'],
            'external_account_id' => $data['external_account_id'],
            'account_name' => $data['account_name'],
            'username' => $data['username'] ?? null,
            'profile_url' => $data['profile_url'] ?? null,
            'token_expires_at' => $data['token_expires_at'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'sync_enabled' => $request->boolean('sync_enabled'),
            'status' => 'connected',
        ];
        if ($creating) {
            $attributes['access_token'] = $data['access_token'];
            $attributes['refresh_token'] = $data['refresh_token'] ?? null;
            $attributes['created_by'] = $request->user()->id;
        }

        return $attributes;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function latestAccountFigures($ids): array
    {
        if ($ids->isEmpty()) {
            return [];
        }
        $rows = \App\Models\SocialMediaNormalizedMetric::query()
            ->whereIn('social_media_account_id', $ids)
            ->where('social_media_post_id', 0)
            ->whereIn('metric_name', ['followers', 'reach', 'engagement'])
            ->orderByDesc('metric_date')
            ->get();
        $latest = [];
        foreach ($rows as $row) {
            $key = $row->social_media_account_id.'.'.$row->metric_name;
            if (isset($latest[$key])) {
                continue;
            }
            $latest[$key] = $row;
        }
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = [
                'followers' => $latest[$id.'.followers'] ?? null,
                'reach' => $latest[$id.'.reach'] ?? null,
            ];
        }

        return $out;
    }
}
