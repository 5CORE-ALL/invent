<?php

namespace App\Http\Controllers\PricingIncDsc;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Models\ChannelMovementAnalysis;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class MasterIncDscController extends Controller
{
    protected $apiController;

    public function __construct()
    {
        $this->apiController = new ApiController();
    }

    public function index()
    {
         $channels = ChannelMovementAnalysis::select('channel_name')
        ->distinct()
        ->get();

        return view('pricing-inc-dsc.master_pricing_inc_dsc', compact('channels')); 
    }

    public function store(Request $request)
    {
        $channel = $request->channel_code;
        foreach ($request->months as $i => $month) {
            $site = $request->site_amounts[$i] ?? 0;
            $receipt = $request->receipt_amounts[$i] ?? 0;
            $expensePct = ($site > 0) ? round(($receipt / $site) * 100, 2) : null;

            ChannelMovementAnalysis::updateOrCreate(
                ['channel_code' => $channel, 'month' => $month],
                [
                    'software_amount' => $request->software_amounts[$i],
                    'site_amount' => $site,
                    'receipt_amount' => $receipt,
                    'expense_percentage' => $expensePct,
                    'ours_percentage' => $request->ours_percentages[$i],
                ]
            );
        }

        return response()->json(['status' => 'success']);
    }

        public function show($channel)
    {
        $months = $this->generateLastNMonths(6); // last 6 months

        // Ensure entries exist for all months for this channel
        foreach ($months as $month) {
            ChannelMovementAnalysis::firstOrCreate([
                'channel_name' => $channel,
                'month' => $month,
            ]);
        }

        $monthlyData = ChannelMovementAnalysis::where('channel_name', $channel)
                        ->orderByDesc('month')
                        ->get();

        return view('channels.channel-movement-analysis-details', compact('channel', 'monthlyData'));
    }

    public function getChannelsFromGoogleSheet()
    {
        $response = $this->apiController->fetchDataFromChannelMasterGoogleSheet();

        if ($response->getStatusCode() === 200) {
            $data = $response->getData();

            $filteredData = array_filter($data->data, function ($item) {
                return !empty(trim($item->{'Channel '} ?? ''));
            });

            $channels = array_values(array_unique(array_map(function ($item) {
                return trim($item->{'Channel '});
            }, $filteredData)));

            return response()->json([
                'status' => 200,
                'channels' => $channels
            ]);
        }

        return response()->json(['status' => 500, 'message' => 'Failed to load channels']);
    }


    public function updateField(Request $request)
    {
        $request->validate([
            'channel' => 'required|string',
            'month' => 'required|string',
            'field' => 'required|in:software_amount,site_amount,receipt_amount,ours_percentage',
            'value' => 'nullable|string|max:255'
        ]);

        $row = ChannelMovementAnalysis::where('channel_name', $request->channel)
                ->where('month', $request->month)
                ->first();

        if (!$row) return response()->json(['message' => 'Not found'], 404);

        $field = $request->field;
        $value = is_numeric($request->value) ? (float) $request->value : $request->value;

        $row->{$field} = $value;

        // Auto-update expense_percentage if site/receipt changed
        if (in_array($field, ['site_amount', 'receipt_amount'])) {
            $site = $field === 'site_amount' ? $value : $row->site_amount;
            $receipt = $field === 'receipt_amount' ? $value : $row->receipt_amount;

            if (is_numeric($site) && $site > 0 && is_numeric($receipt)) {
                $row->expense_percentage = round(($receipt / $site) * 100, 2);
            } else {
                $row->expense_percentage = null;
            }
        }

        $row->save();

        return response()->json([
            'message' => 'Field updated',
            'expense_percentage' => $row->expense_percentage
        ]);
    }


    public function bulkUpdate(Request $request)
    {
        $data = $request->validate([
            'entries' => 'required|array',
            'entries.*.channel' => 'required|string',
            'entries.*.month' => 'required|string',
            'entries.*.software' => 'nullable|numeric',
            'entries.*.site' => 'nullable|numeric',
            'entries.*.receipt' => 'nullable|numeric',
            'entries.*.as_per_ours' => 'nullable|numeric',
        ]);

        foreach ($data['entries'] as $entry) {
            $record = ChannelMovementAnalysis::firstOrNew([
                'channel' => $entry['channel'],
                'month' => $entry['month'],
            ]);

            $record->software = $entry['software'] ?? $record->software;
            $record->site = $entry['site'] ?? $record->site;
            $record->receipt = $entry['receipt'] ?? $record->receipt;
            $record->as_per_ours = $entry['as_per_ours'] ?? $record->as_per_ours;

            if (is_numeric($record->site) && $record->site > 0 && is_numeric($record->receipt)) {
                $record->expense_percentage = round(($record->receipt / $record->site) * 100, 2);
            }

            $record->save();
        }

        return response()->json(['message' => 'All data updated successfully']);
    }


    private function generateLastNMonths($n = 6)
    {
        $months = [];
        for ($i = 0; $i < $n; $i++) {
            $months[] = Carbon::now()->subMonths($i)->format('M-Y');
        }
        return $months;
    }


    public function getMonthlyData($channel)
    {
        try {
            $months = $this->generateLastNMonths(6);

            foreach ($months as $month) {
                ChannelMovementAnalysis::firstOrCreate([
                    'channel_name' => $channel,
                    'month' => $month,
                ]);
            }

            $monthlyData = ChannelMovementAnalysis::where('channel_name', $channel)
                            ->orderByDesc('month')
                            ->get();

            $html = view('channels.monthly-table-rows', compact('monthlyData', 'channel'))->render();

            return response()->json(['html' => $html]);
        } catch (\Exception $e) {
            Log::error('Monthly Data Error: '.$e->getMessage());
            return response()->json(['message' => 'Internal error'], 500);
        }
    }

}
