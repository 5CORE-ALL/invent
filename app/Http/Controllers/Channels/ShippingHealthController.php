<?php

namespace App\Http\Controllers\Channels;

use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use App\Models\ShippingAuditLog;
use App\Models\ShippingHealthValue;
use App\Models\ShippingScopeLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ShippingHealthController extends Controller
{
    private const SCOPE_EBAY_GROUP = 'ebay_group';

    private const EBAY_GROUP_TYPE_SLUGS = [
        'ebay', 'ebay1', 'ebay2', 'ebay3', 'ebaytwo', 'ebaythree',
    ];

    private const EBAY_GROUP_CHANNEL_NAME_SLUGS = [
        'ebay', 'ebay1', 'ebay2', 'ebay3', 'ebaytwo', 'ebaythree',
    ];

    public function tabulator()
    {
        return view('channels.shipping_health.tabulator-master');
    }

    /**
     * Grid data: per-channel today/yesterday value + status + M/H link from shipping_scope_links.
     */
    public function tabulatorChannelData()
    {
        $channels = ChannelMaster::query()
            ->whereRaw('LOWER(TRIM(status)) = ?', ['active'])
            ->orderBy('type')
            ->orderBy('channel')
            ->get(['id', 'channel', 'type', 'status', 'logo']);

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $channelIds = $channels->pluck('id')->all();

        $latestPerDay = [];
        if ($channelIds !== []) {
            $rows = ShippingHealthValue::query()
                ->whereIn('channel_id', $channelIds)
                ->whereIn('recorded_on', [$today, $yesterday])
                ->orderBy('id', 'desc')
                ->get(['channel_id', 'value', 'recorded_on']);
            foreach ($rows as $r) {
                $d = $r->recorded_on instanceof \DateTimeInterface
                    ? $r->recorded_on->format('Y-m-d')
                    : (string) $r->recorded_on;
                $cid = (int) $r->channel_id;
                if (! isset($latestPerDay[$cid])) {
                    $latestPerDay[$cid] = [];
                }
                if (! isset($latestPerDay[$cid][$d])) {
                    $latestPerDay[$cid][$d] = (float) $r->value;
                }
            }
        }

        $scopeLinkCache = [];

        $out = $channels->map(function (ChannelMaster $c) use ($latestPerDay, $today, $yesterday, &$scopeLinkCache) {
            $scope = $this->definitionScopeForChannel($c);
            if (! array_key_exists($scope, $scopeLinkCache)) {
                $row = ShippingScopeLink::query()->where('definition_scope', $scope)->first(['m_link', 'h_link']);
                $scopeLinkCache[$scope] = [
                    'm_link' => $row?->m_link,
                    'h_link' => $row?->h_link,
                ];
            }

            $todayVal = $latestPerDay[$c->id][$today] ?? null;
            $yestVal = $latestPerDay[$c->id][$yesterday] ?? null;

            return [
                'id' => $c->id,
                'channel' => $c->channel,
                'type' => $c->type,
                'status' => $c->status,
                'logo' => $c->logo,
                'm_link' => $scopeLinkCache[$scope]['m_link'] ?: null,
                'h_link' => $scopeLinkCache[$scope]['h_link'] ?: null,
                'shipping_health_today' => $todayVal,
                'shipping_health_yesterday' => $yestVal,
                'shipping_health_status' => $this->shippingHealthStatus($todayVal, $yestVal),
            ];
        });

        return response()->json($out);
    }

    private function shippingHealthStatus(?float $today, ?float $yesterday): string
    {
        if ($today === null) {
            return 'gray';
        }
        if ($yesterday === null) {
            return 'green';
        }
        if ($today > $yesterday) {
            return 'green';
        }
        if ($today < $yesterday) {
            return 'red';
        }

        return 'yellow';
    }

    public function saveValue(Request $request)
    {
        $request->validate([
            'channel_id' => 'required|integer|exists:channel_master,id',
            'value' => 'required|numeric|min:0',
        ]);

        $channelId = (int) $request->input('channel_id');
        $value = round((float) $request->input('value'), 2);
        $now = now();
        $recordedOn = $now->toDateString();
        $userId = Auth::id();

        ShippingHealthValue::query()->updateOrCreate(
            ['channel_id' => $channelId, 'recorded_on' => $recordedOn],
            [
                'value' => $value,
                'recorded_at' => $now,
                'user_id' => $userId,
            ]
        );

        return response()->json([
            'success' => true,
            'channel_id' => $channelId,
            'value' => $value,
            'recorded_on' => $recordedOn,
            'recorded_at' => $now->toDateTimeString(),
            'user_id' => $userId,
        ]);
    }

    public function history(Request $request)
    {
        $request->validate([
            'channel_id' => 'required|integer|exists:channel_master,id',
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        $channelId = (int) $request->input('channel_id');
        $days = (int) ($request->input('days') ?: 30);
        $from = now()->subDays($days - 1)->toDateString();

        $rows = ShippingHealthValue::query()
            ->where('channel_id', $channelId)
            ->whereDate('recorded_on', '>=', $from)
            ->orderBy('recorded_on', 'asc')
            ->orderBy('id', 'asc')
            ->get(['value', 'recorded_on'])
            ->map(function (ShippingHealthValue $r) {
                return [
                    'recorded_on' => $r->recorded_on?->format('Y-m-d'),
                    'value' => (float) $r->value,
                ];
            })->values();

        return response()->json([
            'channel_id' => $channelId,
            'days' => $days,
            'history' => $rows,
        ]);
    }

    public function dailyAverage(Request $request)
    {
        $request->validate([
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        $days = (int) ($request->input('days') ?: 30);
        $from = now()->subDays($days - 1)->toDateString();

        $activeIds = ChannelMaster::query()
            ->whereRaw('LOWER(TRIM(status)) = ?', ['active'])
            ->pluck('id')
            ->all();

        if ($activeIds === []) {
            return response()->json(['days' => $days, 'history' => []]);
        }

        $rows = ShippingHealthValue::query()
            ->whereIn('channel_id', $activeIds)
            ->whereDate('recorded_on', '>=', $from)
            ->selectRaw('recorded_on, AVG(value) AS avg_value, COUNT(*) AS sample_count')
            ->groupBy('recorded_on')
            ->orderBy('recorded_on', 'asc')
            ->get()
            ->map(function ($r) {
                return [
                    'recorded_on' => $r->recorded_on instanceof \DateTimeInterface
                        ? $r->recorded_on->format('Y-m-d')
                        : (string) $r->recorded_on,
                    'avg' => round((float) $r->avg_value, 2),
                    'count' => (int) $r->sample_count,
                ];
            })
            ->values();

        return response()->json([
            'days' => $days,
            'history' => $rows,
        ]);
    }

    public function saveScopeLink(Request $request)
    {
        $request->validate([
            'channel_id' => 'required|integer|exists:channel_master,id',
            'field' => 'required|in:m_link,h_link',
            'value' => 'nullable|string|max:2048',
        ]);

        $channel = ChannelMaster::query()->findOrFail((int) $request->input('channel_id'));
        $scope = $this->definitionScopeForChannel($channel);
        $field = $request->input('field');
        $link = trim((string) $request->input('value', ''));
        if ($link === '') {
            $link = null;
        }

        ShippingScopeLink::query()->updateOrCreate(
            ['definition_scope' => $scope],
            [$field => $link]
        );

        return response()->json([
            'success' => true,
            'scope' => $scope,
            'field' => $field,
            'value' => $link,
        ]);
    }

    public function saveAudit(Request $request)
    {
        $request->validate([
            'channel_id' => 'required|integer|exists:channel_master,id',
            'all_messages_cleared' => 'nullable|boolean',
            'cancelled_orders_not_shipped' => 'nullable|boolean',
            'required_weight_dimensions_declared' => 'nullable|boolean',
            'correct_lowest_label_cost_purchased' => 'nullable|boolean',
            'combined_shipment_message_sent' => 'nullable|boolean',
            'split_shipment_message_tracking_updated' => 'nullable|boolean',
            'auditor_remarks' => 'nullable|string|max:4000',
        ]);

        $row = ShippingAuditLog::query()->create([
            'channel_id' => (int) $request->input('channel_id'),
            'all_messages_cleared' => (bool) $request->boolean('all_messages_cleared'),
            'cancelled_orders_not_shipped' => (bool) $request->boolean('cancelled_orders_not_shipped'),
            'required_weight_dimensions_declared' => (bool) $request->boolean('required_weight_dimensions_declared'),
            'correct_lowest_label_cost_purchased' => (bool) $request->boolean('correct_lowest_label_cost_purchased'),
            'combined_shipment_message_sent' => (bool) $request->boolean('combined_shipment_message_sent'),
            'split_shipment_message_tracking_updated' => (bool) $request->boolean('split_shipment_message_tracking_updated'),
            'auditor_remarks' => trim((string) $request->input('auditor_remarks', '')) ?: null,
            'audited_at' => now(),
            'user_id' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'audit' => $this->presentAudit($row),
        ]);
    }

    public function auditHistory(Request $request)
    {
        $request->validate([
            'channel_id' => 'required|integer|exists:channel_master,id',
            'days' => 'nullable|integer|min:1|max:3650',
        ]);

        $channelId = (int) $request->input('channel_id');
        $q = ShippingAuditLog::query()
            ->with('user:id,name,email')
            ->where('channel_id', $channelId);

        if ($request->filled('days')) {
            $from = now()->subDays((int) $request->input('days'))->startOfDay();
            $q->where('audited_at', '>=', $from);
        }

        $rows = $q->orderByDesc('audited_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (ShippingAuditLog $r) => $this->presentAudit($r))
            ->values();

        return response()->json([
            'channel_id' => $channelId,
            'history' => $rows,
        ]);
    }

    private function presentAudit(ShippingAuditLog $row): array
    {
        return [
            'id' => $row->id,
            'channel_id' => $row->channel_id,
            'all_messages_cleared' => (bool) $row->all_messages_cleared,
            'cancelled_orders_not_shipped' => (bool) $row->cancelled_orders_not_shipped,
            'required_weight_dimensions_declared' => (bool) $row->required_weight_dimensions_declared,
            'correct_lowest_label_cost_purchased' => (bool) $row->correct_lowest_label_cost_purchased,
            'combined_shipment_message_sent' => (bool) $row->combined_shipment_message_sent,
            'split_shipment_message_tracking_updated' => (bool) $row->split_shipment_message_tracking_updated,
            'auditor_remarks' => $row->auditor_remarks,
            'audited_at' => $row->audited_at?->format('Y-m-d H:i'),
            'user' => $row->user ? [
                'id' => $row->user->id,
                'name' => $row->user->name,
            ] : null,
        ];
    }

    // ---- Scope detection (same rules as Account Health: eBay 1/2/3 share one scope) ----

    private function definitionScopeForChannel(ChannelMaster $channel): string
    {
        if ($this->channelBelongsToEbayGroup($channel)) {
            return self::SCOPE_EBAY_GROUP;
        }

        return 'ch_'.$channel->id;
    }

    private function accountHealthChannelSlug(string $value): string
    {
        $v = Str::lower(trim($value));

        return str_replace([' ', '-', '–', '—', '&', '/', '_'], '', $v);
    }

    private function normalizeChannelLabelForCompare(string $value): string
    {
        $s = trim($value);
        $s = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $s) ?? $s;

        return Str::lower($s);
    }

    private function channelNameSlugMatchesEbayGroup(string $chKey): bool
    {
        if ($chKey === '') {
            return false;
        }
        if (in_array($chKey, self::EBAY_GROUP_CHANNEL_NAME_SLUGS, true)) {
            return true;
        }
        $prefixes = ['ebaythree', 'ebaytwo', 'ebay3', 'ebay2', 'ebay1', 'ebay'];
        foreach ($prefixes as $p) {
            if (! str_starts_with($chKey, $p)) {
                continue;
            }
            if ($chKey === $p) {
                return true;
            }
            $next = $chKey[strlen($p)] ?? '';
            if ($next !== '' && ! ctype_alnum($next)) {
                return true;
            }
        }

        return false;
    }

    private function channelBelongsToEbayGroup(ChannelMaster $channel): bool
    {
        $typeRaw = trim((string) ($channel->type ?? ''));
        if ($typeRaw !== '') {
            $typeNorm = $this->accountHealthChannelSlug($typeRaw);
            if (in_array($typeNorm, self::EBAY_GROUP_TYPE_SLUGS, true)) {
                return true;
            }
            if (str_starts_with($typeNorm, 'ebay')) {
                $suffix = substr($typeNorm, 4);
                if ($suffix === '' || $suffix === '1' || $suffix === '2' || $suffix === '3'
                    || $suffix === 'two' || $suffix === 'three') {
                    return true;
                }
            }
        }

        $chKey = $this->accountHealthChannelSlug((string) $channel->channel);
        if ($chKey !== '' && $this->channelNameSlugMatchesEbayGroup($chKey)) {
            return true;
        }

        return false;
    }
}
