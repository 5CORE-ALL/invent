<?php

namespace App\Http\Controllers;

use App\Models\ChannelTabulatorColumnSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChannelTabulatorColumnController extends Controller
{
    /** eBay3 pricing tabulator — fixed channel so existing URLs need no query param. */
    public function showEbay3(): JsonResponse
    {
        return $this->resolveVisibilityResponse('ebay3_tabulator');
    }

    public function storeEbay3(Request $request): JsonResponse
    {
        return $this->persistVisibility($request, 'ebay3_tabulator');
    }

    /**
     * GET /tabulator-column-visibility?channel=your_channel_key
     * Shared column visibility for any Tabulator page (same JSON for all users).
     */
    public function show(Request $request): JsonResponse
    {
        $channel = $this->sanitizeChannelName($request->query('channel'));
        if ($channel === '') {
            return response()->json(['message' => 'Query parameter "channel" is required.'], 422);
        }

        return $this->resolveVisibilityResponse($channel);
    }

    /**
     * POST /tabulator-column-visibility
     * Body: { "channel": "your_channel_key", "visibility": { "field": true, ... } }
     */
    public function store(Request $request): JsonResponse
    {
        $channel = $this->sanitizeChannelName($request->input('channel'));
        if ($channel === '') {
            return response()->json(['message' => 'Field "channel" is required.'], 422);
        }

        return $this->persistVisibility($request, $channel);
    }

    private function resolveVisibilityResponse(string $channel): JsonResponse
    {
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', $channel)
            ->first();

        $visibility = $row && is_array($row->visibility) ? $row->visibility : [];

        return response()->json($visibility);
    }

    private function persistVisibility(Request $request, string $channel): JsonResponse
    {
        $validated = $request->validate([
            'visibility' => 'required|array',
            'visibility.*' => 'boolean',
        ]);

        $normalized = $this->normalizeVisibilityMap($validated['visibility']);

        ChannelTabulatorColumnSetting::query()->updateOrCreate(
            ['channel_name' => $channel],
            ['visibility' => $normalized]
        );

        return response()->json(['success' => true]);
    }

    /**
     * @param  array<string, mixed>  $map
     * @return array<string, bool>
     */
    private function normalizeVisibilityMap(array $map): array
    {
        $out = [];
        foreach ($map as $key => $val) {
            $k = (string) $key;
            if ($k === '' || strlen($k) > 190) {
                continue;
            }
            $out[$k] = (bool) $val;
        }

        return $out;
    }

    private function sanitizeChannelName(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return '';
        }
        $name = substr($name, 0, 120);
        $name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name);

        return $name !== '' ? $name : '';
    }
}
