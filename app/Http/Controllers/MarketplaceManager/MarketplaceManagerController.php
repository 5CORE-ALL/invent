<?php

namespace App\Http\Controllers\MarketplaceManager;

use App\Http\Controllers\Controller;
use App\Models\AlibabaMetric;
use App\Models\AliexpressListingStatus;
use App\Models\AliexpressMetric;
use App\Models\MarketplaceSyncSettings;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\Support\MarketplaceApiConfigService;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class MarketplaceManagerController extends Controller
{
    public function __construct(
        protected MarketplaceApiConfigService $apiConfig
    ) {}

    public function index(): View
    {
        $channels = [];

        foreach (MarketplaceManagerRegistry::channels() as $channel) {
            if (! $channel['enabled']) {
                continue;
            }

            $slug = $channel['slug'];
            $channels[] = array_merge($channel, [
                'connected' => $this->apiConfig->isConfigured($slug),
                'listings_count' => $this->listingCount($slug),
                'sync_settings' => MarketplaceSyncSettings::getFor($slug),
            ]);
        }

        return view('marketplace-manager.index', [
            'title' => 'Marketplace Manager',
            'channels' => $channels,
        ]);
    }

    public function show(string $marketplace): View
    {
        $marketplace = strtolower($marketplace);
        $channel = MarketplaceManagerRegistry::find($marketplace);

        if ($channel === null) {
            abort(404, 'Marketplace not found');
        }

        $settings = MarketplaceSyncSettings::getFor($marketplace);

        return view('marketplace-manager.show', [
            'title' => $channel['label'] . ' — Marketplace Manager',
            'channel' => $channel,
            'connected' => $this->apiConfig->isConfigured($marketplace),
            'listings_count' => $this->listingCount($marketplace),
            'settings' => $settings,
        ]);
    }

    protected function listingCount(string $slug): int
    {
        return match ($slug) {
            'aliexpress' => Schema::hasTable('aliexpress_metric')
                ? (int) AliexpressMetric::query()->whereNotNull('sku')->count()
                : (Schema::hasTable('aliexpress_listing_statuses')
                    ? (int) AliexpressListingStatus::query()->count()
                    : 0),
            'alibaba' => Schema::hasTable('alibaba_metrics')
                ? (int) AlibabaMetric::query()->whereNotNull('sku')->count()
                : 0,
            default => 0,
        };
    }
}
