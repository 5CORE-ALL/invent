<?php

namespace App\Console\Commands;

use App\Models\ChannelDailyCount;
use App\Models\MarketplacePercentage;
use Illuminate\Console\Command;

class SaveChannelDailyCounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:save-channel-daily-counts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Save daily counts for all channels';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get all channels
        $channels = MarketplacePercentage::pluck('marketplace')->toArray();

        // Mapping for special channel/controller names
        $controllerMap = [
            'ebay'          => 'EbayZeroController',
            'ebaythree'     => 'Ebay3ZeroController',
            'ebay3'         => 'Ebay3ZeroController',
            'ebaytwo'       => 'Ebay2ZeroController',
            'ebay2'         => 'Ebay2ZeroController',
            'ebayvariation' => 'EbayVariationZeroController',
            'tiktokshop'    => 'TiktokShopZeroController',
            'doba'          => 'DobaZeroController',
            'walmart'       => 'WalmartZeroController',
            'shein'         => 'SheinZeroController',
            'bestbuyusa'    => 'BestbuyUSAZeroController',
            'aliexpress'    => 'AliexpressZeroController',
            'tiendamia'     => 'TiendamiaZeroController',
            'pls'           => 'PLSZeroController',
            'mercariwship'  => 'MercariWShipZeroController',
            'mercariwoship' => 'MercariWoShipZeroController',
            'instagramshop' => 'InstagramShopZeroController',
            'fbshop'        => 'FBShopZeroController',
            'fbmarketplace' => 'FBMarketplaceZeroController',
            'faire'         => 'FaireZeroController',
            'business5core' => 'Business5CoreZeroController',
            // Add more mappings as needed
        ];

        $livePendingData = [];

        foreach ($channels as $channelName) {
            $livePending = null;

            // Check if channel has special mapping
            $key = strtolower(str_replace([' ', '&', '-', '/'], '', trim($channelName)));
            if (isset($controllerMap[$key])) {
                $controllerName = $controllerMap[$key];
                if ($controllerName === 'EbayZeroController') {
                    $controllerClass = "App\\Http\\Controllers\\MarketPlace\\{$controllerName}";
                } else {
                    $controllerClass = "App\\Http\\Controllers\\MarketPlace\\ZeroViewMarketPlace\\{$controllerName}";
                }
            } else {
                // Build controller class name dynamically (e.g., "Amazon" => AmazonZeroController)
                $baseName = str_replace([' ', '&', '-', '/'], '', ucwords(strtolower(trim($channelName))));
                $controllerClass = "App\\Http\\Controllers\\MarketPlace\\{$baseName}ZeroController";
            }

            if (class_exists($controllerClass)) {
                $controller = app($controllerClass);
                if (method_exists($controller, 'getLivePendingAndZeroViewCounts')) {
                    $counts = $controller->getLivePendingAndZeroViewCounts();
                    $livePending = $counts['live_pending'] ?? null;
                }
            }

            $livePendingData[$channelName] = $livePending;
        }

        // Save today's counts
        $today = now()->toDateString();
        foreach ($livePendingData as $channel => $count) {
            $record = ChannelDailyCount::firstOrNew(['channel_name' => $channel]);
            $counts = $record->counts ?? [];
            $counts[$today] = $count ?? 0;
            $record->counts = $counts;
            $record->save();
        }

        $this->info('Daily counts saved for ' . count($channels) . ' channels.');
    }
}
