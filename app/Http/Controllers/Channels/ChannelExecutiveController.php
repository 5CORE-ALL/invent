<?php

namespace App\Http\Controllers\Channels;

use App\Http\Controllers\Controller;
use App\Models\ChannelExecutive;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ChannelExecutiveController extends Controller
{
    /** Save (or clear) the executive for one channel. */
    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => 'required|string|max:191',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        if (! Schema::hasTable('channel_executives')) {
            return response()->json([
                'success' => false,
                'message' => 'Run php artisan migrate (channel_executives table is missing).',
            ], 500);
        }

        $channel = trim($data['channel']);
        $key = ChannelExecutive::keyFor($channel);
        if ($key === '') {
            return response()->json(['success' => false, 'message' => 'Channel is required.'], 422);
        }

        $userId = $data['user_id'] ?? null;
        if (empty($userId)) {
            ChannelExecutive::where('channel_key', $key)->delete();

            return response()->json(['success' => true, 'data' => ['channel_key' => $key, 'user_id' => null]]);
        }

        ChannelExecutive::updateOrCreate(
            ['channel_key' => $key],
            [
                'channel' => $channel,
                'user_id' => (int) $userId,
                'updated_by' => (string) ($request->user()->name ?? 'unknown'),
            ]
        );

        return response()->json(['success' => true, 'data' => ['channel_key' => $key, 'user_id' => (int) $userId]]);
    }
}
