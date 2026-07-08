<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use App\Models\KpiShippingLink;
use App\Models\KpiShippingAvgHistory;
use App\Models\KpiShippingIncentive;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class KpiShippingController extends Controller
{
    /**
     * The only account allowed to edit the incentive.
     */
    private const INCENTIVE_EDITOR_EMAIL = 'president@5core.com';
    /**
     * Render the Kpi Shipping tabulator view.
     */
    public function tabulator()
    {
        return view('kpi.kpi-shipping-tabulator');
    }

    /**
     * Return JSON rows for the Kpi Shipping tabulator.
     *
     * Channels are loaded directly from the channel_master table.
     */
    public function tabulatorData(Request $request)
    {
        try {
            $channels = ChannelMaster::query()
                ->whereRaw('LOWER(TRIM(status)) = ?', ['active'])
                ->orderBy('channel')
                ->get(['channel']);

            $records = KpiShippingLink::query()
                ->get(['channel', 'link', 'on_time_pct', 'updated_by', 'updated_at'])
                ->keyBy('channel');

            $data = [];
            foreach ($channels as $channel) {
                $name = trim((string) $channel->channel);
                if ($name === '') {
                    continue;
                }

                $record = $records->get($name);

                $data[] = array_merge([                                                                                                                                                                                                                                                                       
                    'channel' => $name,
                    'on_time_pct' => $record && $record->on_time_pct !== null ? (float) $record->on_time_pct : null,
                    'link' => $record->link ?? null,
                ], $this->updatedPayload($record));
            }

            $this->snapshotAverage();

            return response()->json(['data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Snapshot today's average On Time % into the history table.
     */
    private function snapshotAverage(): void
    {
        $avg = KpiShippingLink::query()->whereNotNull('on_time_pct')->avg('on_time_pct');

        if ($avg === null) {
            return;
        }

        KpiShippingAvgHistory::query()->updateOrCreate(
            ['snapshot_date' => Carbon::now('America/Los_Angeles')->toDateString()],
            ['avg_pct' => round((float) $avg, 2)]
        );
    }

    /**
     * History of the average On Time % for the trend chart.
     */
    public function avgHistory(Request $request)
    {
        try {
            $days = (int) $request->input('days', 30);

            $query = KpiShippingAvgHistory::query()->orderBy('snapshot_date', 'asc');
            if ($days > 0) {
                $start = Carbon::now('America/Los_Angeles')->subDays($days)->toDateString();
                $query->where('snapshot_date', '>=', $start);
            }

            $data = $query->get()->map(function ($row) {
                return [
                    'date' => Carbon::parse($row->snapshot_date)->format('M d'),
                    'value' => round((float) $row->avg_pct, 2),
                ];
            })->values();

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Build the "updated" payload (who + when + staleness) for a record.
     *
     * @return array<string, mixed>
     */
    private function updatedPayload(?KpiShippingLink $record): array
    {
        if (!$record || !$record->updated_at) {
            return [
                'updated_by' => null,
                'updated_at_ts' => null,
                'updated_display' => null,
                'is_stale' => false,
            ];
        }

        $updatedAt = Carbon::parse($record->updated_at);
        $by = trim((string) ($record->updated_by ?? '')) ?: 'Unknown';

        return [
            'updated_by' => $record->updated_by,
            'updated_at_ts' => $updatedAt->timestamp,
            'updated_display' => $by . ' · ' . $updatedAt->format('j M'),
            'is_stale' => $updatedAt->lt(Carbon::now()->subDays(7)),
        ];
    }

    /**
     * Create or update the link for a channel.
     */
    public function saveLink(Request $request)
    {
        $request->validate([
            'channel' => 'required|string|max:191',
            'link' => 'nullable|string|max:2048',
        ]);

        $channel = trim((string) $request->input('channel'));
        $link = trim((string) $request->input('link', ''));
        if ($link === '') {
            $link = null;
        }

        $record = KpiShippingLink::query()->updateOrCreate(
            ['channel' => $channel],
            ['link' => $link, 'updated_by' => optional(Auth::user())->name]
        );

        return response()->json(array_merge([
            'success' => true,
            'channel' => $channel,
            'link' => $link,
        ], $this->updatedPayload($record)));
    }

    /**
     * Create or update the "Label Created & Uploaded On Time %" value for a channel.
     */
    public function saveValue(Request $request)
    {
        $request->validate([
            'channel' => 'required|string|max:191',
            'on_time_pct' => 'nullable|numeric|min:0|max:100',
        ]);

        $channel = trim((string) $request->input('channel'));
        $value = $request->input('on_time_pct');
        $value = ($value === null || $value === '') ? null : (float) $value;

        $record = KpiShippingLink::query()->updateOrCreate(
            ['channel' => $channel],
            ['on_time_pct' => $value, 'updated_by' => optional(Auth::user())->name]
        );

        $this->snapshotAverage();

        return response()->json(array_merge([
            'success' => true,
            'channel' => $channel,
            'on_time_pct' => $value,
        ], $this->updatedPayload($record)));
    }

    /**
     * Whether the current user may edit the incentive.
     */
    private function canEditIncentive(): bool
    {
        $user = Auth::user();

        return $user && strtolower((string) $user->email) === self::INCENTIVE_EDITOR_EMAIL;
    }

    /**
     * List of all per-user incentive rows (for the badge summary and modal table).
     *
     * @return array<int, array<string, mixed>>
     */
    private function incentiveList(): array
    {
        $records = KpiShippingIncentive::query()->whereNotNull('user_id')->get();
        $userMap = User::query()
            ->whereIn('id', $records->pluck('user_id')->all())
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        return $records->map(function ($record) use ($userMap) {
            $user = $userMap->get($record->user_id);

            return [
                'user_id' => $record->user_id,
                'user_label' => $user ? $user->name . ' (' . $user->email . ')' : ('User #' . $record->user_id),
                'user_name' => $user ? $user->name : ('User #' . $record->user_id),
                'target' => $record->target,
                'amount' => $record->amount !== null ? (float) $record->amount : null,
                'condition' => $record->condition,
            ];
        })->sortBy('user_name')->values()->all();
    }

    /**
     * Return all per-user incentives plus the user list and edit permission.
     */
    public function incentive()
    {
        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'label' => $user->name . ' (' . $user->email . ')',
                ];
            });

        return response()->json([
            'incentives' => $this->incentiveList(),
            'can_edit' => $this->canEditIncentive(),
            'users' => $users,
        ]);
    }

    /**
     * Create or update a user's incentive (president@5core.com only).
     */
    public function saveIncentive(Request $request)
    {
        if (!$this->canEditIncentive()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to edit the incentive.',
            ], 403);
        }

        $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
            'target' => 'nullable|string|max:100',
            'amount' => 'nullable|numeric|min:0',
            'condition' => 'nullable|string|max:100',
        ]);

        $userIds = array_values(array_unique(array_map('intval', $request->input('user_ids'))));
        $target = trim((string) $request->input('target', ''));
        $amount = $request->input('amount');
        $condition = trim((string) $request->input('condition', ''));

        $attributes = [
            'target' => $target === '' ? null : $target,
            'amount' => ($amount === null || $amount === '') ? null : (float) $amount,
            'condition' => $condition === '' ? null : $condition,
            'updated_by' => optional(Auth::user())->name,
        ];

        foreach ($userIds as $userId) {
            KpiShippingIncentive::query()->updateOrCreate(['user_id' => $userId], $attributes);
        }

        return response()->json([
            'success' => true,
            'incentives' => $this->incentiveList(),
        ]);
    }
}
