<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\AmazonChannelSummary;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AnalyticsDilBadgeController extends Controller
{
    public function chart(Request $request)
    {
        $channel = $this->normalizeChannel($request->input('channel'));
        if ($channel === '') {
            return response()->json(['success' => false, 'message' => 'Channel is required'], 400);
        }

        $days = (int) $request->input('days', 30);
        $query = AmazonChannelSummary::query()
            ->where('channel', $channel)
            ->orderBy('snapshot_date');
        if ($days > 0) {
            $query->whereDate(
                'snapshot_date',
                '>=',
                now('America/Los_Angeles')->subDays(max(0, $days - 1))->toDateString()
            );
        }

        $chartData = [];
        foreach ($query->get() as $row) {
            $sd = is_array($row->summary_data) ? $row->summary_data : [];
            if (! array_key_exists('dil_ov_percent', $sd) && ! array_key_exists('dil_percent', $sd)) {
                $inv = (float) ($sd['total_inv'] ?? $sd['total_fba_inv'] ?? 0);
                $ov = (float) ($sd['total_ov_l30'] ?? $sd['total_l30'] ?? 0);
                if ($inv <= 0 && $ov <= 0) {
                    continue;
                }
                $val = $inv > 0 ? round(($ov / $inv) * 100, 2) : 0.0;
            } else {
                $val = (float) ($sd['dil_ov_percent'] ?? $sd['dil_percent'] ?? 0);
            }

            $ymd = $row->snapshot_date instanceof \DateTimeInterface
                ? $row->snapshot_date->format('Y-m-d')
                : Carbon::parse((string) $row->snapshot_date)->toDateString();

            $chartData[] = [
                'date' => Carbon::parse($ymd)->format('M d'),
                'full_date' => $ymd,
                'value' => $val,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $chartData,
            'metric' => 'dil_ov_percent',
        ]);
    }

    public function prevDay(Request $request)
    {
        $channel = $this->normalizeChannel($request->input('channel'));
        if ($channel === '') {
            return response()->json(['success' => false, 'message' => 'Channel is required'], 400);
        }

        $yesterday = now('America/Los_Angeles')->subDay()->toDateString();
        $row = AmazonChannelSummary::query()
            ->where('channel', $channel)
            ->whereDate('snapshot_date', '<=', $yesterday)
            ->orderByDesc('snapshot_date')
            ->first();

        $dil = null;
        if ($row) {
            $sd = is_array($row->summary_data) ? $row->summary_data : [];
            if (array_key_exists('dil_ov_percent', $sd) || array_key_exists('dil_percent', $sd)) {
                $dil = (float) ($sd['dil_ov_percent'] ?? $sd['dil_percent'] ?? 0);
            } else {
                $inv = (float) ($sd['total_inv'] ?? $sd['total_fba_inv'] ?? 0);
                $ov = (float) ($sd['total_ov_l30'] ?? $sd['total_l30'] ?? 0);
                $dil = $inv > 0 ? round(($ov / $inv) * 100, 2) : null;
            }
        }

        return response()->json([
            'success' => true,
            'metrics' => ['dil_ov_percent' => $dil],
        ]);
    }

    public function snapshot(Request $request)
    {
        $channel = $this->normalizeChannel($request->input('channel'));
        if ($channel === '') {
            return response()->json(['success' => false, 'message' => 'Channel is required'], 400);
        }

        $dil = round((float) $request->input('dil_ov_percent', 0), 2);
        $ovL30 = round((float) $request->input('total_ov_l30', 0), 2);
        $inv = round((float) $request->input('total_inv', 0), 2);
        $today = now('America/Los_Angeles')->toDateString();

        $row = AmazonChannelSummary::firstOrNew([
            'channel' => $channel,
            'snapshot_date' => $today,
        ]);
        $sd = is_array($row->summary_data) ? $row->summary_data : [];
        $sd['dil_ov_percent'] = $dil;
        $sd['total_ov_l30'] = $ovL30;
        $sd['total_inv'] = $inv;
        $sd['dil_updated_at'] = now()->toDateTimeString();
        $row->summary_data = $sd;
        if ($row->notes === null || $row->notes === '') {
            $row->notes = 'Dil% daily snapshot';
        }
        $row->save();

        return response()->json(['success' => true]);
    }

    private function normalizeChannel(mixed $raw): string
    {
        $channel = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) $raw) ?? '');

        return match ($channel) {
            'ebaytwo' => 'ebay2',
            'ebaythree' => 'ebay3',
            'ebayone', 'ebay1' => 'ebay',
            'temutwo' => 'temu2',
            'temuthree' => 'temu3',
            default => $channel,
        };
    }
}
