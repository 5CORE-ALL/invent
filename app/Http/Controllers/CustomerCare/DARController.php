<?php

namespace App\Http\Controllers\CustomerCare;

use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use App\Models\DailyActivityReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DARController extends Controller
{
    private const TZ = 'America/Los_Angeles';

    public static function isWithinSubmissionWindow(?Carbon $moment = null): bool
    {
        $now = $moment ?? Carbon::now(self::TZ);
        $minutes = ((int) $now->format('H')) * 60 + (int) $now->format('i');

        return $minutes >= 16 * 60 && $minutes < 17 * 60;
    }

    /**
     * // TODO: Fetch channels via API
     */
    public function index()
    {
        $userId = Auth::id();
        // Align with Active Channels Master (channel_master, active)
        $channels = ChannelMaster::query()
            ->whereRaw('LOWER(TRIM(status)) = ?', ['active'])
            ->orderBy('type')
            ->orderBy('id')
            ->get(['id', 'channel']);

        if ($channels->isEmpty()) {
            $channels = collect([
                (object) ['id' => 0, 'channel' => 'Amazon'],
                (object) ['id' => 0, 'channel' => 'Shopify'],
                (object) ['id' => 0, 'channel' => 'Website'],
            ]);
        }

        $todayLa = Carbon::now(self::TZ)->toDateString();
        $submittedToday = DailyActivityReport::query()
            ->where('user_id', $userId)
            ->where('report_date', $todayLa)
            ->get()
            ->keyBy('channel_id');

        $channelIds = $channels->pluck('id')->filter(fn ($id) => (int) $id > 0)->values()->all();
        $lastByChannel = collect();
        if ($channelIds !== []) {
            $lastByChannel = DailyActivityReport::query()
                ->where('user_id', $userId)
                ->whereIn('channel_id', $channelIds)
                ->select('channel_id', DB::raw('MAX(submitted_at) as last_submitted'))
                ->groupBy('channel_id')
                ->pluck('last_submitted', 'channel_id');
        }

        $channelRows = $channels->map(function ($ch) use ($submittedToday, $lastByChannel, $todayLa) {
            $id = (int) $ch->id;
            $submitted = $id > 0 && $submittedToday->has($id);
            $last = $lastByChannel[$id] ?? null;

            return [
                'id' => $id,
                'name' => $ch->channel,
                'last_submitted' => $last ? Carbon::parse($last)->timezone(self::TZ)->format('M j, Y g:i A T') : '—',
                'last_submitted_raw' => $last,
                'status' => $submitted ? 'submitted' : 'pending',
                'submitted_today' => $submitted,
            ];
        });

        [$chartLabels, $chartCounts, $chartAvgMinutes] = $this->buildChartData();

        return view('customer-care.dar', [
            'channelRows' => $channelRows,
            'chartLabels' => $chartLabels,
            'chartCounts' => $chartCounts,
            'chartAvgMinutes' => $chartAvgMinutes,
            'inDarWindow' => self::isWithinSubmissionWindow(),
            'todayLa' => $todayLa,
        ]);
    }

    private function buildChartData(): array
    {
        $start = Carbon::now(self::TZ)->subDays(29)->startOfDay();

        $rows = DailyActivityReport::query()
            ->where('report_date', '>=', $start->toDateString())
            ->get(['report_date', 'submitted_at']);

        $byDate = [];
        $sumMinutes = [];
        $countForAvg = [];

        foreach ($rows as $row) {
            $d = $row->report_date instanceof Carbon
                ? $row->report_date->format('Y-m-d')
                : $row->report_date;
            $byDate[$d] = ($byDate[$d] ?? 0) + 1;

            $la = Carbon::parse($row->submitted_at)->timezone(self::TZ);
            $mins = (int) $la->format('H') * 60 + (int) $la->format('i');
            $sumMinutes[$d] = ($sumMinutes[$d] ?? 0) + $mins;
            $countForAvg[$d] = ($countForAvg[$d] ?? 0) + 1;
        }

        $labels = [];
        $counts = [];
        $avgLine = [];

        for ($i = 29; $i >= 0; $i--) {
            $day = Carbon::now(self::TZ)->subDays($i)->toDateString();
            $labels[] = Carbon::parse($day)->format('M j');
            $counts[] = $byDate[$day] ?? 0;
            if (!empty($countForAvg[$day])) {
                $avgM = (int) round($sumMinutes[$day] / $countForAvg[$day]);
                $avgLine[] = round($avgM / 60, 2);
            } else {
                $avgLine[] = null;
            }
        }

        if (array_sum($counts) === 0) {
            $labels = ['Mar 10', 'Mar 11', 'Mar 12', 'Mar 13', 'Mar 14', 'Mar 15', 'Mar 16'];
            $counts = [1, 0, 2, 3, 1, 2, 4];
            $avgLine = [16.25, null, 16.42, 16.33, 16.5, 16.2, 16.4];
        }

        return [$labels, $counts, $avgLine];
    }

    public function windowStatus()
    {
        $now = Carbon::now(self::TZ);

        return response()->json([
            'in_window' => self::isWithinSubmissionWindow($now),
            'timezone' => self::TZ,
            'now_display' => $now->format('g:i A T'),
            'message' => self::isWithinSubmissionWindow($now)
                ? 'Submission window is open (4–5 PM California Time).'
                : 'DAR can only be submitted between 4 PM – 5 PM California Time.',
        ]);
    }

    /**
     * // TODO: Submit DAR via API
     */
    public function store(Request $request)
    {
        $request->validate([
            'channel_id' => ['required', 'integer'],
        ]);

        if (!self::isWithinSubmissionWindow()) {
            return response()->json([
                'success' => false,
                'message' => 'DAR can only be submitted between 4 PM – 5 PM California Time.',
            ], 422);
        }

        $channelId = (int) $request->channel_id;
        if ($channelId > 0) {
            $ok = ChannelMaster::query()
                ->where('id', $channelId)
                ->whereRaw('LOWER(TRIM(status)) = ?', ['active'])
                ->exists();
            if (!$ok) {
                return response()->json(['success' => false, 'message' => 'Invalid channel.'], 422);
            }
        } else {
            return response()->json(['success' => false, 'message' => 'Please select a valid channel.'], 422);
        }

        $userId = Auth::id();
        $todayLa = Carbon::now(self::TZ)->toDateString();

        if (DailyActivityReport::query()
            ->where('user_id', $userId)
            ->where('channel_id', $channelId)
            ->where('report_date', $todayLa)
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You have already submitted DAR for this channel today.',
            ], 422);
        }

        $responsibilities = [
            'messaging' => [
                'responded_to_customer_queries' => $request->boolean('messaging_responded_queries'),
                'followed_up_pending_tickets' => $request->boolean('messaging_followed_tickets'),
                'cleared_inbox_messages' => $request->boolean('messaging_cleared_inbox'),
            ],
            'returns_refunds' => [
                'processed_return_requests' => $request->boolean('rr_processed_returns'),
                'initiated_refunds' => $request->boolean('rr_initiated_refunds'),
                'verified_return_cases' => $request->boolean('rr_verified_returns'),
            ],
            'escalations' => [
                'handled_escalations' => $request->boolean('esc_handled'),
                'reported_critical_issues' => $request->boolean('esc_reported_critical'),
            ],
            'general' => [
                'updated_crm' => $request->boolean('gen_updated_crm'),
                'internal_team_coordination' => $request->boolean('gen_internal_coord'),
                'other' => $request->boolean('gen_other'),
                'other_text' => $request->input('gen_other_text', ''),
            ],
        ];

        $anyChecked = in_array(true, [
            $request->boolean('messaging_responded_queries'),
            $request->boolean('messaging_followed_tickets'),
            $request->boolean('messaging_cleared_inbox'),
            $request->boolean('rr_processed_returns'),
            $request->boolean('rr_initiated_refunds'),
            $request->boolean('rr_verified_returns'),
            $request->boolean('esc_handled'),
            $request->boolean('esc_reported_critical'),
            $request->boolean('gen_updated_crm'),
            $request->boolean('gen_internal_coord'),
            $request->boolean('gen_other'),
        ], true);

        $hasActivity = $anyChecked
            || ($request->boolean('gen_other') && strlen(trim((string) $request->input('gen_other_text'))) > 0)
            || strlen(trim((string) $request->input('comments'))) > 0;

        if (!$hasActivity) {
            return response()->json([
                'success' => false,
                'message' => 'Please select at least one responsibility or add comments.',
            ], 422);
        }

        $report = DailyActivityReport::create([
            'user_id' => $userId,
            'channel_id' => $channelId,
            'report_date' => $todayLa,
            'responsibilities' => $responsibilities,
            'comments' => $request->input('comments'),
            'submitted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Daily Activity Report submitted successfully.',
            'id' => $report->id,
        ]);
    }
}
