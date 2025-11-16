<?php

namespace App\Http\Controllers\Channels;

use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use App\Models\ShippingRate;
use Illuminate\Http\Request;

class ShippingMasterController extends Controller
{
    public function index(){
        return view('channels.shipping-master');
    }

    public function fetchShippingRate()
    {
        $channels = ChannelMaster::all()->keyBy('id');
        
        $rates = ShippingRate::all()->groupBy('channel_id');

        $allLbsFields = collect(range(0.25, 0.75, 0.25))
            ->merge(range(1, 20))
            ->map(function ($val) {
                $key = 'w_' . str_replace('.', '_', $val) . '_lbs';
                return $key;
            });

        $data = $channels->map(function ($channel) use ($rates, $allLbsFields) {
            $channelRates = $rates[$channel->id] ?? collect();

            if ($channelRates->isEmpty()) {
                $emptyRow = [
                    'channel' => $channel->channel,
                    'channel_id' => $channel->id,
                    'rate_id' => null,
                    'carrier_name' => '',
                    'updation_date' => null,
                ];

                foreach ($allLbsFields as $field) {
                    $emptyRow[$field] = '';
                }

                return [$emptyRow];
            }

            return $channelRates->map(function ($rate) use ($channel, $allLbsFields) {
                $row = [
                    'channel' => $channel->channel,
                    'channel_id' => $channel->id,
                    'rate_id' => $rate->id,
                    'carrier_name' => $rate->carrier_name,
                    'updation_date' => $rate->updation_date,
                ];

                foreach ($allLbsFields as $field) {
                    $row[$field] = $rate->lbs_values[$field] ?? '';
                }

                return $row;
            })->values()->all();
        });

        return response()->json($data->collapse()->values());
    }


    public function storeOrUpdateShippingRate(Request $request)
    {
        $id = $request->input('id');
        $channelId = $request->input('channel_id');
        $field = $request->input('field');
        $value = $request->input('value');

        if (!$field || is_null($value)) {
            return response()->json(['message' => 'Invalid request'], 400);
        }

        if ($id) {
            $rate = ShippingRate::find($id);
            if (!$rate) {
                return response()->json(['message' => 'Shipping Rate not found'], 404);
            }
        } else {
            $rate = ShippingRate::where('channel_id', $channelId)->first();

            if (!$rate) {
                $rate = new ShippingRate();
                $rate->channel_id = $channelId;
                $rate->lbs_values = [];
            }
        }

        if ($field === 'carrier_name') {
            $rate->carrier_name = $value;
        } else {
            $lbsValues = $rate->lbs_values;
            if (is_string($lbsValues)) {
                $lbsValues = json_decode($lbsValues, true);
            }
            $lbsValues[$field] = $value;
            $rate->lbs_values = $lbsValues;
        }

        $rate->updation_date = now();
        $rate->save();

        return response()->json(['message' => 'Rate saved/updated successfully', 'data' => $rate]);
    }




}
