<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Promotions — Tabulator of active channels from channel_master
 * (same source as /all-marketplace-master Active Channels Master).
 *
 * First three columns:
 *   - Img      (channel_master.logo)
 *   - Channels (channel_master.channel)
 *   - Status   (channel_master.status — active only)
 *
 * Promotion-specific columns can be appended after these.
 */
class PromotionsController extends Controller
{
    public function index()
    {
        return view('market-places.promotions');
    }

    public function data(Request $request)
    {
        try {
            if (! Schema::hasTable('channel_master')) {
                return response()->json(['success' => true, 'data' => [], 'count' => 0]);
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

            $data = $rows->map(function ($row) use ($hasLogo, $hasSellerLink, $hasAlias) {
                return [
                    'id' => $row->id,
                    'image' => $hasLogo ? ($row->logo ?? null) : null,
                    'channel' => trim((string) $row->channel),
                    'alias' => $hasAlias ? ($row->alias ?? null) : null,
                    'seller_link' => $hasSellerLink ? ($row->seller_link ?? null) : null,
                    'status' => 'Active',
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $data,
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
}
