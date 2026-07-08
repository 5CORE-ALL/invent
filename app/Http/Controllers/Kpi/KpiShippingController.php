<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use Illuminate\Http\Request;

class KpiShippingController extends Controller
{
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

            $data = [];
            foreach ($channels as $channel) {
                $name = trim((string) $channel->channel);
                if ($name === '') {
                    continue;
                }

                $data[] = [
                    'channel' => $name,
                    'on_time_pct' => 0,
                ];
            }

            return response()->json(['data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
