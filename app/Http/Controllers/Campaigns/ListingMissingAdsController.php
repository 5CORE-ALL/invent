<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

abstract class ListingMissingAdsController extends Controller
{
    abstract protected function cacheKey(): string;

    abstract protected function pageTitle(): string;

    abstract protected function pageSubtitle(): string;

    abstract protected function adsUrl(): string;

    abstract protected function adsLabel(): string;

    abstract protected function idField(): string;

    abstract protected function idLabel(): string;

    /**
     * @return Collection<int, array<string, mixed>>
     */
    abstract protected function collectMissingRows(bool $withImages = true): Collection;

    public function index()
    {
        return view('campaign.listing-missing-ads', [
            'pageTitle' => $this->pageTitle(),
            'pageSubtitle' => $this->pageSubtitle(),
            'adsUrl' => $this->adsUrl(),
            'adsLabel' => $this->adsLabel(),
            'idField' => $this->idField(),
            'idLabel' => $this->idLabel(),
            'dataUrl' => route(static::dataRouteName()),
        ]);
    }

    abstract public static function dataRouteName(): string;

    public static function missingTotalCount(): int
    {
        $key = (new static)->cacheKey();
        try {
            $cached = Cache::get($key);
            if ($cached !== null) {
                return (int) $cached;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            $total = (new static)->countMissing();
            try {
                Cache::put($key, $total, now()->addMinutes(5));
            } catch (\Throwable $e) {
                // ignore
            }

            return $total;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function data(): JsonResponse
    {
        $rows = $this->collectMissingRows(true);
        try {
            Cache::put($this->cacheKey(), $rows->count(), now()->addMinutes(5));
        } catch (\Throwable $e) {
            // ignore
        }

        return response()->json([
            'data' => $rows->values(),
            'total' => $rows->count(),
        ]);
    }

    protected function countMissing(): int
    {
        return $this->collectMissingRows(false)->count();
    }
}
