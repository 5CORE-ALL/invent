<?php

namespace App\Http\Controllers\Channels;

use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use App\Models\CustomerCareHealthChannelData;
use App\Models\CustomerCareHealthStatusHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Account Health "Customer Care Health" grid (Escalated Claims–style).
 * Uses customer_care_health_channel_data — separate from CC Message Health.
 */
class CustomerCareHealthController extends Controller
{
    private const HISTORY_FRESH_DAYS = 3;

    public function tabulator()
    {
        return view('channels.customer_care_health.tabulator');
    }

    public function tabulatorChannelData()
    {
        $rows = $this->buildChannelRows();
        $this->snapshotStatusCounts($rows);

        return response()->json($rows);
    }

    public function statusSummary()
    {
        $counts = $this->countTones($this->buildChannelRows());
        $this->snapshotStatusCountsFromCounts($counts);

        return response()->json([
            'success' => true,
            'counts' => $counts,
            'total' => array_sum($counts),
            'rated_total' => $counts['red'] + $counts['yellow'] + $counts['green'],
        ]);
    }

    public function statusHistory(Request $request)
    {
        $days = (int) $request->input('days', 60);
        if ($days < 1) {
            $days = 60;
        }
        if ($days > 365) {
            $days = 365;
        }

        $tone = strtolower(trim((string) $request->input('tone', '')));
        if (! in_array($tone, ['red', 'yellow', 'green', ''], true)) {
            $tone = '';
        }

        $this->snapshotStatusCounts($this->buildChannelRows());

        $start = Carbon::now()->subDays($days - 1)->startOfDay();
        $rows = CustomerCareHealthStatusHistory::query()
            ->where('snapshot_date', '>=', $start->toDateString())
            ->orderBy('snapshot_date')
            ->get()
            ->keyBy(function (CustomerCareHealthStatusHistory $row) {
                return $row->snapshot_date instanceof \DateTimeInterface
                    ? $row->snapshot_date->format('Y-m-d')
                    : (string) $row->snapshot_date;
            });

        $data = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i);
            $key = $date->toDateString();
            /** @var CustomerCareHealthStatusHistory|null $row */
            $row = $rows->get($key);
            $data[] = [
                'date' => $date->format('M j'),
                'snapshot_date' => $key,
                'red' => $row ? (int) $row->red_count : 0,
                'yellow' => $row ? (int) $row->yellow_count : 0,
                'green' => $row ? (int) $row->green_count : 0,
            ];
        }

        return response()->json([
            'success' => true,
            'days' => $days,
            'tone' => $tone !== '' ? $tone : null,
            'data' => $data,
        ]);
    }

    public function saveLink(Request $request)
    {
        $request->validate([
            'channel_id' => 'required|integer|exists:channel_master,id',
            'link' => 'nullable|string|max:2048',
            'required_parameter' => 'nullable|numeric|min:0|max:100',
            'current_parameter' => 'nullable|numeric|min:0|max:100',
            'summary_issues' => 'nullable|string|max:5000',
            'root_cause_found' => 'nullable|string|max:5000',
            'action_to_fix' => 'nullable|string|max:5000',
        ]);

        $channelId = (int) $request->input('channel_id');
        $link = trim((string) $request->input('link', ''));
        if ($link === '') {
            $link = null;
        }

        $required = $request->input('required_parameter');
        $required = ($required === null || $required === '') ? null : (float) $required;

        $current = $request->input('current_parameter');
        $current = ($current === null || $current === '') ? null : (float) $current;

        $summaryIssues = trim((string) $request->input('summary_issues', ''));
        $summaryIssues = $summaryIssues === '' ? null : $summaryIssues;

        $rootCause = trim((string) $request->input('root_cause_found', ''));
        $rootCause = $rootCause === '' ? null : $rootCause;

        $actionToFix = trim((string) $request->input('action_to_fix', ''));
        $actionToFix = $actionToFix === '' ? null : $actionToFix;

        $record = CustomerCareHealthChannelData::query()->updateOrCreate(
            ['channel_id' => $channelId],
            [
                'link' => $link,
                'required_parameter' => $required,
                'current_parameter' => $current,
                'summary_issues' => $summaryIssues,
                'root_cause_found' => $rootCause,
                'action_to_fix' => $actionToFix,
                'updated_by' => optional(Auth::user())->name,
            ]
        );
        $record->touch();
        $record->refresh();

        $this->snapshotStatusCounts($this->buildChannelRows());

        return response()->json(array_merge([
            'success' => true,
            'channel_id' => $channelId,
            'link' => $link,
            'required_parameter' => $required,
            'current_parameter' => $current,
            'summary_issues' => $summaryIssues,
            'root_cause_found' => $rootCause,
            'action_to_fix' => $actionToFix,
            'status_tone' => $this->parameterTone($current, $required),
        ], $this->historyPayload($record)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildChannelRows(): array
    {
        $channels = ChannelMaster::query()
            ->whereRaw('LOWER(TRIM(status)) = ?', ['active'])
            ->orderBy('type')
            ->orderBy('channel')
            ->get(['id', 'channel', 'type', 'status', 'logo']);

        $linkRows = CustomerCareHealthChannelData::query()
            ->whereIn('channel_id', $channels->pluck('id'))
            ->get([
                'channel_id',
                'link',
                'required_parameter',
                'current_parameter',
                'summary_issues',
                'root_cause_found',
                'action_to_fix',
                'updated_by',
                'updated_at',
            ])
            ->keyBy('channel_id');

        return $channels->map(function (ChannelMaster $c) use ($linkRows) {
            /** @var CustomerCareHealthChannelData|null $row */
            $row = $linkRows->get($c->id);
            $history = $this->historyPayload($row);

            $required = $row && $row->required_parameter !== null
                ? (float) $row->required_parameter
                : null;
            $current = $row && $row->current_parameter !== null
                ? (float) $row->current_parameter
                : null;

            return array_merge([
                'id' => $c->id,
                'channel' => $c->channel,
                'type' => $c->type,
                'status' => $c->status,
                'logo' => $c->logo,
                'required_parameter' => $required,
                'current_parameter' => $current,
                'status_tone' => $this->parameterTone($current, $required),
                'link' => $row?->link ?: null,
                'summary_issues' => $row?->summary_issues ?: null,
                'root_cause_found' => $row?->root_cause_found ?: null,
                'action_to_fix' => $row?->action_to_fix ?: null,
            ], $history);
        })->values()->all();
    }

    private function parameterTone(?float $current, ?float $required): ?string
    {
        if ($current === null || $required === null) {
            return null;
        }

        if ($current < $required) {
            return 'red';
        }

        $halfOfDifference = $required + ((100 - $required) * 0.5);
        if ($current < $halfOfDifference) {
            return 'yellow';
        }

        return 'green';
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{red: int, yellow: int, green: int, unrated: int}
     */
    private function countTones(array $rows): array
    {
        $counts = [
            'red' => 0,
            'yellow' => 0,
            'green' => 0,
            'unrated' => 0,
        ];

        foreach ($rows as $row) {
            $tone = $row['status_tone'] ?? null;
            if ($tone === 'red' || $tone === 'yellow' || $tone === 'green') {
                $counts[$tone]++;
            } else {
                $counts['unrated']++;
            }
        }

        return $counts;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function snapshotStatusCounts(array $rows): void
    {
        $this->snapshotStatusCountsFromCounts($this->countTones($rows));
    }

    /**
     * @param  array{red: int, yellow: int, green: int, unrated?: int}  $counts
     */
    private function snapshotStatusCountsFromCounts(array $counts): void
    {
        CustomerCareHealthStatusHistory::query()->updateOrCreate(
            ['snapshot_date' => Carbon::now()->toDateString()],
            [
                'red_count' => (int) ($counts['red'] ?? 0),
                'yellow_count' => (int) ($counts['yellow'] ?? 0),
                'green_count' => (int) ($counts['green'] ?? 0),
            ]
        );
    }

    /**
     * @return array{updated_by: ?string, updated_at_ts: ?int, history_display: ?string, is_stale: bool}
     */
    private function historyPayload(?CustomerCareHealthChannelData $record): array
    {
        if (! $record || ! $record->updated_at) {
            return [
                'updated_by' => null,
                'updated_at_ts' => null,
                'history_display' => null,
                'is_stale' => false,
            ];
        }

        $updatedAt = Carbon::parse($record->updated_at);
        $by = trim((string) ($record->updated_by ?? '')) ?: 'Unknown';

        return [
            'updated_by' => $record->updated_by,
            'updated_at_ts' => $updatedAt->timestamp,
            'history_display' => $by.' · '.strtoupper($updatedAt->format('j M')),
            'is_stale' => $updatedAt->lt(Carbon::now()->subDays(self::HISTORY_FRESH_DAYS)),
        ];
    }
}
