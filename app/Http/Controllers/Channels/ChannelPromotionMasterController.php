<?php

namespace App\Http\Controllers\Channels;

use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use App\Models\ChannelPromotion;
use Illuminate\Http\Request;

class ChannelPromotionMasterController extends Controller
{
    /**
     * Show the promotion master index with channels data.
     */
    public function channel_promotion_master_index()
    {
        // Get all channels from ChannelMaster
        $channels = ChannelMaster::all();

        // Fetch all ChannelPromotion records and index by channel name for quick lookup
        $promotions = ChannelPromotion::all()->keyBy('channels');

        // Merge promotion values into each channel (if exists)
        $channels = $channels->map(function ($channel) use ($promotions) {
            $promotion = $promotions->get($channel->channel);
            if ($promotion && is_array($promotion->value)) {
                foreach ($promotion->value as $key => $val) {
                    $channel->$key = $val;
                }
            }
            return $channel;
        });

        return view('channels.channel-promotion', compact('channels'));
    }

    /**
     * Store or update the promotion for a channel.
     */
    public function storeOrUpdatePromotion(Request $request)
    {
        $request->validate([
            'channels' => 'required|string',
            'value' => 'required|array',
        ]);

        // Save or update by channel name
        $promotion = ChannelPromotion::updateOrCreate(
            ['channels' => $request->channels],
            ['value' => $request->value]
        );

        return response()->json(['success' => true, 'promotion' => $promotion]);
    }
}
