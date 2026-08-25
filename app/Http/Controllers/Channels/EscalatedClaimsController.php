<?php

namespace App\Http\Controllers\Channels;

use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use App\Models\EscalatedClaimsLink;
use App\Models\EscalatedClaimsStatusHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class EscalatedClaimsController extends Controller
{
    private const HISTORY_FRESH_DAYS = 3;

    public function tabulator()
    {
        return view('channels.escalated_claims.tabulator');
    }

    /**
     * Grid data: active Channel Master rows + parameters, link, history, root cause.
     */
    public function tabulatorChannelData()
    {
        $rows = $this->buildChannelRows();
        $this->snapshotStatusCounts($rows);

        return response()->json($rows);
    }

    /**
     * Red / yellow / green counts for pie charts (Escalated Claims + Account Health dashboard).
     */
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

    /**
     * Last N days of red / yellow / green counts for the status history chart.
     */
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
        $rows = EscalatedClaimsStatusHistory::query()
            ->where('snapshot_date', '>=', $start->toDateString())
            ->orderBy('snapshot_date')
            ->get()
            ->keyBy(function (EscalatedClaimsStatusHistory $row) {
                return $row->snapshot_date instanceof \DateTimeInterface
                    ? $row->snapshot_date->format('Y-m-d')
                    : (string) $row->snapshot_date;
            });

        $data = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i);
            $key = $date->toDateString();
            /** @var EscalatedClaimsStatusHistory|null $row */
            $row = $rows->get($key);
            $point = [
                'date' => $date->format('M j'),
                'snapshot_date' => $key,
                'red' => $row ? (int) $row->red_count : 0,
                'yellow' => $row ? (int) $row->yellow_count : 0,
                'green' => $row ? (int) $row->green_count : 0,
            ];
            $data[] = $point;
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
            'cases' => 'nullable|array',
            'cases.*.case_id' => 'nullable|string|max:255',
            'cases.*.summary' => 'nullable|string|max:5000',
            'cases.*.root_cause' => 'nullable|string|max:5000',
            'cases.*.action' => 'nullable|string|max:5000',
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

        $cases = $this->normalizeCases($request->input('cases'));
        if ($cases === []) {
            $cases = $this->normalizeCases([], [
                'summary' => $request->input('summary_issues'),
                'root_cause' => $request->input('root_cause_found'),
                'action' => $request->input('action_to_fix'),
            ]);
        }

        $flat = $this->flattenCases($cases);
        $summaryIssues = $flat['summary_issues'];
        $rootCause = $flat['root_cause_found'];
        $actionToFix = $flat['action_to_fix'];

        $payload = [
            'link' => $link,
            'required_parameter' => $required,
            'current_parameter' => $current,
            'summary_issues' => $summaryIssues,
            'root_cause_found' => $rootCause,
            'action_to_fix' => $actionToFix,
            'updated_by' => optional(Auth::user())->name,
        ];
        if ($this->hasCasesColumn()) {
            $payload['cases'] = $cases === [] ? null : $cases;
        }

        $record = EscalatedClaimsLink::query()->updateOrCreate(
            ['channel_id' => $channelId],
            $payload
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
            'cases' => $cases,
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

        $select = [
            'channel_id',
            'link',
            'required_parameter',
            'current_parameter',
            'summary_issues',
            'root_cause_found',
            'action_to_fix',
            'updated_by',
            'updated_at',
        ];
        if ($this->hasCasesColumn()) {
            $select[] = 'cases';
        }

        $linkRows = EscalatedClaimsLink::query()
            ->whereIn('channel_id', $channels->pluck('id'))
            ->get($select)
            ->keyBy('channel_id');

        return $channels->map(function (ChannelMaster $c) use ($linkRows) {
            /** @var EscalatedClaimsLink|null $row */
            $row = $linkRows->get($c->id);
            $history = $this->historyPayload($row);
            $cases = $this->hydrateCases($row);

            $required = $row && $row->required_parameter !== null
                ? (float) $row->required_parameter
                : null;
            $current = $row && $row->current_parameter !== null
                ? (float) $row->current_parameter
                : null;

            $flat = $this->flattenCases($cases);

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
                'summary_issues' => $flat['summary_issues'] ?: ($row?->summary_issues ?: null),
                'root_cause_found' => $flat['root_cause_found'] ?: ($row?->root_cause_found ?: null),
                'action_to_fix' => $flat['action_to_fix'] ?: ($row?->action_to_fix ?: null),
                'cases' => $cases,
            ], $history);
        })->values()->all();
    }

    /**
     * Red = below required; yellow = meets required but below 50% of gap to 100%; green = otherwise.
     */
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
        EscalatedClaimsStatusHistory::query()->updateOrCreate(
            ['snapshot_date' => Carbon::now()->toDateString()],
            [
                'red_count' => (int) ($counts['red'] ?? 0),
                'yellow_count' => (int) ($counts['yellow'] ?? 0),
                'green_count' => (int) ($counts['green'] ?? 0),
            ]
        );
    }

    private function hasCasesColumn(): bool
    {
        return Schema::hasColumn('escalated_claims_links', 'cases');
    }

    /**
     * @param  mixed  $input
     * @param  array{case_id?: mixed, summary?: mixed, root_cause?: mixed, action?: mixed}|null  $fallback
     * @return list<array{case_id: ?string, summary: ?string, root_cause: ?string, action: ?string}>
     */
    private function normalizeCases($input, ?array $fallback = null): array
    {
        $cases = [];
        if (is_array($input)) {
            foreach ($input as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $caseId = trim((string) ($row['case_id'] ?? ''));
                $summary = trim((string) ($row['summary'] ?? $row['summary_issues'] ?? ''));
                $root = trim((string) ($row['root_cause'] ?? $row['root_cause_found'] ?? ''));
                $action = trim((string) ($row['action'] ?? $row['action_to_fix'] ?? ''));
                if ($caseId === '' && $summary === '' && $root === '' && $action === '') {
                    continue;
                }
                $cases[] = [
                    'case_id' => $caseId !== '' ? $caseId : null,
                    'summary' => $summary !== '' ? $summary : null,
                    'root_cause' => $root !== '' ? $root : null,
                    'action' => $action !== '' ? $action : null,
                ];
            }
        }

        if ($cases === [] && is_array($fallback)) {
            $caseId = trim((string) ($fallback['case_id'] ?? ''));
            $summary = trim((string) ($fallback['summary'] ?? ''));
            $root = trim((string) ($fallback['root_cause'] ?? ''));
            $action = trim((string) ($fallback['action'] ?? ''));
            if ($caseId !== '' || $summary !== '' || $root !== '' || $action !== '') {
                $cases[] = [
                    'case_id' => $caseId !== '' ? $caseId : null,
                    'summary' => $summary !== '' ? $summary : null,
                    'root_cause' => $root !== '' ? $root : null,
                    'action' => $action !== '' ? $action : null,
                ];
            }
        }

        return $cases;
    }

    /**
     * @param  list<array{case_id: ?string, summary: ?string, root_cause: ?string, action: ?string}>  $cases
     * @return array{summary_issues: ?string, root_cause_found: ?string, action_to_fix: ?string}
     */
    private function flattenCases(array $cases): array
    {
        $summaries = [];
        $roots = [];
        $actions = [];

        foreach ($cases as $i => $case) {
            $label = ! empty($case['case_id']) ? ('Case '.$case['case_id']) : ('Case '.($i + 1));
            if (! empty($case['summary'])) {
                $summaries[] = $label.': '.$case['summary'];
            }
            if (! empty($case['root_cause'])) {
                $roots[] = $label.': '.$case['root_cause'];
            }
            if (! empty($case['action'])) {
                $actions[] = $label.': '.$case['action'];
            }
        }

        return [
            'summary_issues' => $summaries === [] ? null : implode("\n", $summaries),
            'root_cause_found' => $roots === [] ? null : implode("\n", $roots),
            'action_to_fix' => $actions === [] ? null : implode("\n", $actions),
        ];
    }

    /**
     * @return list<array{case_id: ?string, summary: ?string, root_cause: ?string, action: ?string}>
     */
    private function hydrateCases(?EscalatedClaimsLink $row): array
    {
        if (! $row) {
            return [];
        }

        $stored = $row->cases;
        if (is_string($stored)) {
            $decoded = json_decode($stored, true);
            $stored = is_array($decoded) ? $decoded : [];
        }

        return $this->normalizeCases(is_array($stored) ? $stored : [], [
            'summary' => $row->summary_issues,
            'root_cause' => $row->root_cause_found,
            'action' => $row->action_to_fix,
        ]);
    }

    /**
     * @return array{updated_by: ?string, updated_at_ts: ?int, history_display: ?string, is_stale: bool}
     */
    private function historyPayload(?EscalatedClaimsLink $record): array
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
