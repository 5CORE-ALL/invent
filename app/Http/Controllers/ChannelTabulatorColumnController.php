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
     * GET /tabulator-column-visibility-user?channel=your_channel_key
     * Per-user column visibility (scoped to the authenticated user).
     */
    public function showUser(Request $request): JsonResponse
    {
        $channel = $this->sanitizeChannelName($request->query('channel'));
        if ($channel === '') {
            return response()->json(['message' => 'Query parameter "channel" is required.'], 422);
        }

        return $this->resolveVisibilityResponse($this->userScopedChannel($channel));
    }

    /**
     * POST /tabulator-column-visibility-user
     * Body: { "channel": "your_channel_key", "visibility": { "field": true, ... } }
     * Stored per authenticated user.
     */
    public function storeUser(Request $request): JsonResponse
    {
        $channel = $this->sanitizeChannelName($request->input('channel'));
        if ($channel === '') {
            return response()->json(['message' => 'Field "channel" is required.'], 422);
        }

        return $this->persistVisibility($request, $this->userScopedChannel($channel));
    }

    private function userScopedChannel(string $channel): string
    {
        return 'u'.(int) (auth()->id() ?? 0).'__'.$channel;
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

    /**
     * GET /tabulator-column-order?channel=your_channel_key
     * Shared column drag-order for any Tabulator page (same for all users).
     */
    public function showOrder(Request $request): JsonResponse
    {
        $channel = $this->sanitizeChannelName($request->query('channel'));
        if ($channel === '') {
            return response()->json(['message' => 'Query parameter "channel" is required.'], 422);
        }

        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', $channel)
            ->first();

        $order = ($row && is_array($row->column_order)) ? array_values($row->column_order) : [];

        return response()->json([
            'success' => true,
            'order' => $this->normalizeColumnOrder($order),
        ]);
    }

    /**
     * POST /tabulator-column-order
     * Body: { "channel": "your_channel_key", "order": ["field1", "field2", ...] }
     */
    public function storeOrder(Request $request): JsonResponse
    {
        $channel = $this->sanitizeChannelName($request->input('channel'));
        if ($channel === '') {
            return response()->json(['message' => 'Field "channel" is required.'], 422);
        }

        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'string|max:190',
        ]);

        $normalized = $this->normalizeColumnOrder($validated['order']);

        ChannelTabulatorColumnSetting::query()->updateOrCreate(
            ['channel_name' => $channel],
            ['column_order' => $normalized]
        );

        return response()->json(['success' => true, 'order' => $normalized]);
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
     * @param  array<int, mixed>  $order
     * @return list<string>
     */
    private function normalizeColumnOrder(array $order): array
    {
        $out = [];
        $seen = [];
        foreach ($order as $field) {
            $f = trim((string) $field);
            if ($f === '' || strlen($f) > 190 || isset($seen[$f])) {
                continue;
            }
            $seen[$f] = true;
            $out[] = $f;
        }

        return $out;
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
