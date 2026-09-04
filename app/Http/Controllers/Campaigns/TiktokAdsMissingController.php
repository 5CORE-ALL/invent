<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\Grandparent;
use App\Models\TiktokGParent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Same grandparents list as YouTube Missing Ads, but parent links are stored
 * separately in tiktok_g_parents (independent of youtube_g_parents).
 */
class TiktokAdsMissingController extends Controller
{
    private const SIDEBAR_COUNT_CACHE_KEY = 'tiktok_ads_missing_sidebar_count';

    public function index()
    {
        return view('campaign.tiktok_missing_ads');
    }

    /**
     * Grandparents with no linked parent — same list as /tiktok/ads/missing.
     */
    public static function missingTotalCount(): int
    {
        try {
            $cached = Cache::get(self::SIDEBAR_COUNT_CACHE_KEY);
            if ($cached !== null) {
                return (int) $cached;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            $total = self::computeMissingTotal();
            try {
                Cache::put(self::SIDEBAR_COUNT_CACHE_KEY, $total, now()->addMinutes(5));
            } catch (\Throwable $e) {
                // ignore
            }

            return $total;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function forgetMissingTotalCache(): void
    {
        try {
            Cache::forget(self::SIDEBAR_COUNT_CACHE_KEY);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private static function computeMissingTotal(): int
    {
        if (! Schema::hasTable('grandparents')) {
            return 0;
        }

        $linked = [];
        if (Schema::hasTable('tiktok_g_parents')) {
            foreach (TiktokGParent::query()->get(['g_parent']) as $row) {
                $key = strtoupper(preg_replace('/\s+/', ' ', trim((string) $row->g_parent)));
                if ($key !== '') {
                    $linked[$key] = true;
                }
            }
        }

        $missing = 0;
        foreach (Grandparent::query()
            ->whereNotNull('grandparent')
            ->where('grandparent', '!=', '')
            ->get(['grandparent']) as $row
        ) {
            $key = strtoupper(preg_replace('/\s+/', ' ', trim((string) $row->grandparent)));
            if ($key !== '' && ! isset($linked[$key])) {
                $missing++;
            }
        }

        return $missing;
    }

    public function data(): JsonResponse
    {
        if (! Schema::hasTable('grandparents')) {
            return response()->json(['data' => []]);
        }

        $linksByGParent = collect();
        if (Schema::hasTable('tiktok_g_parents')) {
            $linksByGParent = TiktokGParent::query()
                ->orderBy('parent')
                ->get(['id', 'parent', 'g_parent'])
                ->groupBy(fn ($row) => strtoupper(preg_replace('/\s+/', ' ', trim((string) $row->g_parent))));
        }

        $data = Grandparent::query()
            ->whereNotNull('grandparent')
            ->where('grandparent', '!=', '')
            ->orderBy('grandparent')
            ->get(['id', 'grandparent'])
            ->map(function ($row) use ($linksByGParent) {
                $name = (string) $row->grandparent;
                $key = strtoupper(preg_replace('/\s+/', ' ', trim($name)));
                $links = $linksByGParent->get($key, collect());

                return [
                    'id' => (int) $row->id,
                    'grandparent' => $name,
                    'parents' => $links->map(fn ($l) => [
                        'id' => (int) $l->id,
                        'parent' => (string) $l->parent,
                    ])->values()->all(),
                ];
            })
            ->values();

        return response()->json(['data' => $data]);
    }

    public function createGrandparent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'grandparent' => ['required', 'string', 'max:255'],
        ]);

        if (! Schema::hasTable('grandparents')) {
            return response()->json(['ok' => false, 'message' => 'grandparents table missing.'], 500);
        }

        $name = preg_replace('/\s+/', ' ', trim($validated['grandparent']));
        if ($name === '') {
            return response()->json(['ok' => false, 'message' => 'Grandparent name is required.'], 422);
        }

        $exists = Grandparent::query()
            ->whereRaw('UPPER(TRIM(grandparent)) = ?', [strtoupper($name)])
            ->first();

        if ($exists) {
            return response()->json([
                'ok' => false,
                'message' => 'Grandparent already exists.',
                'row' => [
                    'id' => (int) $exists->id,
                    'grandparent' => (string) $exists->grandparent,
                    'parents' => $this->parentsForGrandparent((string) $exists->grandparent),
                ],
            ], 422);
        }

        $row = Grandparent::create(['grandparent' => $name]);
        self::forgetMissingTotalCache();
        GoogleYoutubeAdsMissingController::forgetMissingTotalCache();

        return response()->json([
            'ok' => true,
            'row' => [
                'id' => (int) $row->id,
                'grandparent' => (string) $row->grandparent,
                'parents' => [],
            ],
        ]);
    }

    public function searchParents(Request $request): JsonResponse
    {
        $q = preg_replace('/\s+/', ' ', trim((string) $request->query('q', '')));
        if (! Schema::hasTable('product_master') || mb_strlen($q) < 1) {
            return response()->json(['data' => []]);
        }

        $rows = DB::table('product_master')
            ->select('parent', 'sku', 'Values')
            ->whereNull('deleted_at')
            ->whereNotNull('parent')
            ->where('parent', '!=', '')
            ->where('parent', 'like', '%'.$q.'%')
            ->orderBy('parent')
            ->limit(400)
            ->get();

        $parents = [];
        foreach ($rows as $r) {
            $sku = trim((string) ($r->sku ?? ''));
            if ($sku === '' || Str::startsWith(strtoupper($sku), 'PARENT') || $this->isDcProduct($r)) {
                continue;
            }
            $parent = preg_replace('/\s+/', ' ', trim((string) ($r->parent ?? '')));
            if ($parent === '') {
                continue;
            }
            $parents[strtoupper($parent)] = $parent;
            if (count($parents) >= 50) {
                break;
            }
        }
        ksort($parents);

        return response()->json([
            'data' => collect($parents)->values()->map(fn ($p) => ['parent' => $p])->all(),
        ]);
    }

    public function linkParent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'grandparent' => ['required', 'string', 'max:255'],
            'parent' => ['required', 'string', 'max:255'],
        ]);

        if (! Schema::hasTable('tiktok_g_parents')) {
            return response()->json(['ok' => false, 'message' => 'tiktok_g_parents table missing. Run migrations.'], 500);
        }

        $grandparent = preg_replace('/\s+/', ' ', trim($validated['grandparent']));
        $parent = preg_replace('/\s+/', ' ', trim($validated['parent']));
        if ($grandparent === '' || $parent === '') {
            return response()->json(['ok' => false, 'message' => 'Grandparent and Parent are required.'], 422);
        }

        $row = TiktokGParent::updateOrCreate(
            ['parent' => $parent],
            [
                'g_parent' => $grandparent,
                'user_id' => Auth::id(),
            ]
        );
        self::forgetMissingTotalCache();

        return response()->json([
            'ok' => true,
            'grandparent' => $grandparent,
            'link' => [
                'id' => (int) $row->id,
                'parent' => (string) $row->parent,
            ],
            'parents' => $this->parentsForGrandparent($grandparent),
        ]);
    }

    public function unlinkParent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        if (! Schema::hasTable('tiktok_g_parents')) {
            return response()->json(['ok' => true, 'parents' => []]);
        }

        $row = TiktokGParent::query()->find($validated['id']);
        $grandparent = $row?->g_parent;
        if ($row) {
            $row->delete();
            self::forgetMissingTotalCache();
        }

        return response()->json([
            'ok' => true,
            'grandparent' => (string) ($grandparent ?? ''),
            'parents' => $grandparent ? $this->parentsForGrandparent((string) $grandparent) : [],
        ]);
    }

    /**
     * @return list<array{id: int, parent: string}>
     */
    private function parentsForGrandparent(string $grandparent): array
    {
        $key = strtoupper(preg_replace('/\s+/', ' ', trim($grandparent)));

        return TiktokGParent::query()
            ->orderBy('parent')
            ->get(['id', 'parent', 'g_parent'])
            ->filter(fn ($l) => strtoupper(preg_replace('/\s+/', ' ', trim((string) $l->g_parent))) === $key)
            ->map(fn ($l) => [
                'id' => (int) $l->id,
                'parent' => (string) $l->parent,
            ])
            ->values()
            ->all();
    }

    private function isDcProduct(object $row): bool
    {
        $values = $row->Values ?? null;
        if (is_string($values)) {
            $values = json_decode($values, true);
        }
        if (! is_array($values)) {
            return false;
        }

        return strtoupper(trim((string) ($values['status'] ?? ''))) === 'DC';
    }
}
