<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\MarketplacePercentage;
use App\Support\SchemaMemo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MarketplacePercentageController extends Controller
{
    private const EDITOR_EMAIL = 'president@5core.com';

    public function index(): View
    {
        $rows = MarketplacePercentage::query()
            ->whereNotNull('percentage')
            ->where('percentage', '>', 0)
            ->orderBy('marketplace')
            ->get()
            ->map(fn (MarketplacePercentage $row) => $this->payload($row))
            ->values();

        return view('market-places.marketplace_percentage', [
            'rows' => $rows,
            'canEdit' => $this->canEdit(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($denied = $this->denyUnlessEditor()) {
            return $denied;
        }

        $data = $request->validate([
            'marketplace' => 'required|string|max:255',
            'percentage' => 'required|numeric|min:0|max:100',
            'ad_updates' => 'nullable|numeric|min:0',
        ]);

        $name = trim($data['marketplace']);
        if ($name === '') {
            return response()->json(['success' => false, 'message' => 'Marketplace name is required.'], 422);
        }

        $existing = $this->findByName($name);
        if ($existing && ! $existing->trashed()) {
            return response()->json(['success' => false, 'message' => 'That marketplace already exists.'], 422);
        }

        $attributes = [
            'marketplace' => $name,
            'percentage' => round((float) $data['percentage'], 2),
        ];
        if ($this->hasAdUpdates() && isset($data['ad_updates']) && $data['ad_updates'] !== '') {
            $attributes['ad_updates'] = $this->numberString((float) $data['ad_updates']);
        }

        if ($existing) {
            $existing->restore();
            $existing->fill($attributes);
            $existing->save();
            $row = $existing;
        } else {
            $row = DB::transaction(function () use ($attributes) {
                $nextId = (int) MarketplacePercentage::withTrashed()->lockForUpdate()->max('id') + 1;
                $created = new MarketplacePercentage($attributes);
                $created->incrementing = false;
                $created->id = $nextId;
                $created->save();

                return $created;
            });
        }

        $this->forgetCaches($name);

        return response()->json([
            'success' => true,
            'message' => 'Marketplace added.',
            'row' => $this->payload($row),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->denyUnlessEditor()) {
            return $denied;
        }

        $row = MarketplacePercentage::query()->findOrFail($id);
        $allowed = $this->hasAdUpdates()
            ? 'marketplace,percentage,ad_updates'
            : 'marketplace,percentage';

        $data = $request->validate([
            'field' => 'required|in:'.$allowed,
            'value' => 'present',
        ]);

        $field = $data['field'];
        $previousName = (string) $row->marketplace;

        if ($field === 'marketplace') {
            $name = trim((string) $data['value']);
            if ($name === '') {
                return response()->json(['success' => false, 'message' => 'Marketplace name is required.'], 422);
            }
            $dup = $this->findByName($name, $row->id);
            if ($dup) {
                return response()->json(['success' => false, 'message' => 'That marketplace already exists.'], 422);
            }
            $row->marketplace = $name;
        } elseif ($field === 'percentage') {
            if (! is_numeric($data['value']) || (float) $data['value'] < 0 || (float) $data['value'] > 100) {
                return response()->json(['success' => false, 'message' => 'Enter a number from 0 to 100.'], 422);
            }
            $row->percentage = round((float) $data['value'], 2);
        } else {
            $raw = is_string($data['value']) ? trim($data['value']) : $data['value'];
            if ($raw === '' || $raw === null) {
                $row->ad_updates = null;
            } elseif (! is_numeric($raw) || (float) $raw < 0) {
                return response()->json(['success' => false, 'message' => 'Ad % must be a number that is 0 or higher.'], 422);
            } else {
                $row->ad_updates = $this->numberString((float) $raw);
            }
        }

        $row->save();
        $this->forgetCaches($previousName);
        if ((string) $row->marketplace !== $previousName) {
            $this->forgetCaches((string) $row->marketplace);
        }

        return response()->json([
            'success' => true,
            'message' => 'Saved.',
            'row' => $this->payload($row->fresh()),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        if ($denied = $this->denyUnlessEditor()) {
            return $denied;
        }

        $row = MarketplacePercentage::query()->findOrFail($id);
        $name = (string) $row->marketplace;
        $row->delete();
        $this->forgetCaches($name);

        return response()->json([
            'success' => true,
            'message' => $name.' removed.',
        ]);
    }

    private function canEdit(): bool
    {
        return strtolower(trim((string) (auth()->user()->email ?? ''))) === self::EDITOR_EMAIL;
    }

    private function denyUnlessEditor(): ?JsonResponse
    {
        if ($this->canEdit()) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Only president@5core.com can edit Marketplace %.',
        ], 403);
    }

    private function hasAdUpdates(): bool
    {
        return SchemaMemo::hasColumn('marketplace_percentages', 'ad_updates');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(MarketplacePercentage $row): array
    {
        $percentage = round((float) $row->percentage, 2);
        $ad = $this->numericOrNull($row->ad_updates);

        return [
            'id' => $row->id,
            'marketplace' => $row->marketplace,
            'percentage' => $percentage,
            'ad_updates' => $ad,
            'net' => round($percentage - ($ad ?? 0), 2),
            'updated_at' => optional($row->updated_at)->format('M j, Y g:i A'),
        ];
    }

    private function numericOrNull(mixed $value): ?float
    {
        if (is_string($value)) {
            $value = trim($value);
        }
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }

    private function numberString(float $value): string
    {
        $formatted = number_format(round($value, 2), 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    private function findByName(string $name, ?int $exceptId = null): ?MarketplacePercentage
    {
        $query = MarketplacePercentage::withTrashed()
            ->whereRaw('LOWER(TRIM(marketplace)) = ?', [strtolower(trim($name))]);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->first();
    }

    private function forgetCaches(string $marketplace): void
    {
        $lower = strtolower(trim($marketplace));
        $compact = preg_replace('/[^a-z0-9]/', '', $lower) ?? $lower;
        $nospace = str_replace(' ', '', $lower);

        $keys = [
            $compact.'_marketplace_percentage',
            $compact.'_marketplace_ad_updates',
            $nospace.'_marketplace_percentage',
            $nospace.'_marketplace_ad_updates',
        ];

        $aliases = [
            'ebay' => ['Ebay', 'Ebay_ad_updates'],
            'ebaytwo' => ['Ebay2', 'Ebay2_ad_updates', 'ebay2_marketplace_percentage'],
            'ebaythree' => ['Ebay3', 'Ebay3_ad_updates', 'ebay3_marketplace_percentage'],
            'instagramshop' => ['instagram_marketplace_percentage'],
            'instagram' => ['instagramshop_marketplace_percentage'],
            'fbmarketplace' => ['fb_marketplace_marketplace_percentage'],
            'temu2' => ['temutwo_marketplace_percentage'],
            'temutwo' => ['temu2_marketplace_percentage'],
            'temu3' => ['temuthree_marketplace_percentage'],
            'temuthree' => ['temu3_marketplace_percentage'],
        ];

        foreach ($aliases[$compact] ?? [] as $alias) {
            $keys[] = $alias;
        }

        foreach (array_unique($keys) as $key) {
            Cache::forget($key);
        }
    }
}
