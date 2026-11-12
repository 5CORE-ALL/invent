<?php

namespace App\Http\Controllers\Channels;

use App\Http\Controllers\Controller;
use App\Models\ChannelVariationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ChannelVariationTypeController extends Controller
{
    /** Save (or clear) the Variation / Single setting for one channel. */
    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => 'required|string|max:191',
            'value' => 'nullable|string|in:Variation,Single',
        ]);

        if (! Schema::hasTable('channel_variation_types')) {
            return response()->json([
                'success' => false,
                'message' => 'Run php artisan migrate (channel_variation_types table is missing).',
            ], 500);
        }

        $channel = trim($data['channel']);
        $key = ChannelVariationType::keyFor($channel);
        if ($key === '') {
            return response()->json(['success' => false, 'message' => 'Channel is required.'], 422);
        }

        $value = $data['value'] ?? null;
        if ($value === null || $value === '') {
            ChannelVariationType::where('channel_key', $key)->delete();

            return response()->json(['success' => true, 'data' => ['channel_key' => $key, 'value' => '']]);
        }

        ChannelVariationType::updateOrCreate(
            ['channel_key' => $key],
            [
                'channel' => $channel,
                'variation_type' => $value,
                'updated_by' => (string) ($request->user()->name ?? 'unknown'),
            ]
        );

        return response()->json(['success' => true, 'data' => ['channel_key' => $key, 'value' => $value]]);
    }
}
