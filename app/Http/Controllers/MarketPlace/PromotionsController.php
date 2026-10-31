<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Promotions — Tabulator of active channels from channel_master
 * (same source as /all-marketplace-master Active Channels Master).
 *
 * Column order:
 *   Img | Channels | Status | <dynamic columns…> | Notes and Remarks | Edit (pen)
 *
 * Dynamic columns live in promotion_columns; their per-channel values and the
 * Notes and Remarks text live in promotion_channel_data.
 */
class PromotionsController extends Controller
{
    public function index()
    {
        return view('market-places.promotions');
    }

    private function storageReady(): bool
    {
        return Schema::hasTable('promotion_columns') && Schema::hasTable('promotion_channel_data');
    }

    public function data(Request $request)
    {
        try {
            if (! Schema::hasTable('channel_master')) {
                return response()->json(['success' => true, 'data' => [], 'columns' => [], 'count' => 0]);
            }

            $hasLogo = Schema::hasColumn('channel_master', 'logo');
            $hasSellerLink = Schema::hasColumn('channel_master', 'seller_link');
            $hasAlias = Schema::hasColumn('channel_master', 'alias');

            $columns = ['id', 'channel', 'status'];
            if ($hasLogo) {
                $columns[] = 'logo';
            }
            if ($hasSellerLink) {
                $columns[] = 'seller_link';
            }
            if ($hasAlias) {
                $columns[] = 'alias';
            }

            $rows = ChannelMaster::query()
                ->whereRaw('LOWER(TRIM(status)) = ?', ['active'])
                ->whereNotNull('channel')
                ->where('channel', '!=', '')
                ->orderBy('channel')
                ->get($columns);

            $dynamicColumns = [];
            $extra = collect();
            if ($this->storageReady()) {
                $dynamicColumns = DB::table('promotion_columns')
                    ->orderBy('position')
                    ->orderBy('id')
                    ->get(['id', 'name'])
                    ->map(fn ($c) => ['id' => (int) $c->id, 'name' => $c->name])
                    ->values()
                    ->all();

                $extra = DB::table('promotion_channel_data')
                    ->whereIn('channel_master_id', $rows->pluck('id'))
                    ->get()
                    ->keyBy('channel_master_id');
            }

            $data = $rows->map(function ($row) use ($hasLogo, $hasSellerLink, $hasAlias, $extra) {
                $stored = $extra->get($row->id);
                $values = $stored && $stored->values ? (json_decode($stored->values, true) ?: []) : [];

                return [
                    'id' => $row->id,
                    'image' => $hasLogo ? ($row->logo ?? null) : null,
                    'channel' => trim((string) $row->channel),
                    'alias' => $hasAlias ? ($row->alias ?? null) : null,
                    'seller_link' => $hasSellerLink ? ($row->seller_link ?? null) : null,
                    'status' => 'Active',
                    'notes_remarks' => $stored->notes_remarks ?? '',
                    'values' => (object) $values,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $data,
                'columns' => $dynamicColumns,
                'count' => $data->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Promotions data failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a dynamic column with a user-chosen header name.
     */
    public function storeColumn(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:120',
        ]);

        if (! $this->storageReady()) {
            return response()->json([
                'success' => false,
                'message' => 'Promotions tables are missing. Run php artisan migrate.',
            ], 500);
        }

        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return response()->json(['success' => false, 'message' => 'Column name is required.'], 422);
        }

        $reserved = ['img', 'channels', 'channel', 'status', 'notes and remarks', 'edit'];
        $lower = mb_strtolower($name);
        $exists = DB::table('promotion_columns')->whereRaw('LOWER(name) = ?', [$lower])->exists();
        if ($exists || in_array($lower, $reserved, true)) {
            return response()->json(['success' => false, 'message' => 'A column with this name already exists.'], 422);
        }

        $position = (int) DB::table('promotion_columns')->max('position') + 1;
        $id = DB::table('promotion_columns')->insertGetId([
            'name' => $name,
            'position' => $position,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'column' => ['id' => $id, 'name' => $name],
        ]);
    }

    /**
     * Save Notes and Remarks + every dynamic column value for one channel (pen button).
     */
    public function saveRow(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:channel_master,id',
            'notes_remarks' => 'nullable|string|max:5000',
            'values' => 'nullable|array',
            'values.*' => 'nullable|string|max:2000',
        ]);

        if (! $this->storageReady()) {
            return response()->json([
                'success' => false,
                'message' => 'Promotions tables are missing. Run php artisan migrate.',
            ], 500);
        }

        $validIds = DB::table('promotion_columns')->pluck('id')->map(fn ($v) => (string) $v)->all();
        $values = [];
        foreach ((array) $request->input('values', []) as $columnId => $value) {
            if (in_array((string) $columnId, $validIds, true)) {
                $values[(string) $columnId] = $value === null ? '' : (string) $value;
            }
        }

        $channelId = $request->integer('id');
        $notes = (string) $request->input('notes_remarks', '');

        DB::table('promotion_channel_data')->updateOrInsert(
            ['channel_master_id' => $channelId],
            [
                'notes_remarks' => $notes,
                'values' => json_encode((object) $values),
                'updated_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $channelId,
                'notes_remarks' => $notes,
                'values' => (object) $values,
            ],
        ]);
    }
}
