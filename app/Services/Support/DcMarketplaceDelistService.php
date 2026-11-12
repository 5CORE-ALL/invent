<?php

namespace App\Services\Support;

use App\Models\Task;
use App\Services\AmazonSpApiService;
use App\Services\TaskWhatsAppNotificationService;
use App\Support\Marketplace\ChannelListingRegistry;
use App\Support\Marketplace\ListingCountsEngine;
use App\Support\Marketplace\ListingManagerEbayTradingPublisher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * When SKUs are marked DC: remove them from every marketplace the app can delete from
 * (eBay 1/2/3, Amazon, Shopify), then hand everything it could not do to ecomm2 as ONE task
 * listing each SKU with its failed and not-connected marketplaces.
 *
 * "Not connected" = a marketplace where the SKU is listed in our records but the app has no
 * delete integration (or no credentials) for it, so someone has to remove it by hand.
 */
class DcMarketplaceDelistService
{
    public const ASSIGNOR = 'president@5core.com';

    public const ASSIGNEE = 'ecomm2@5core.com';

    public const GROUP = 'DC Delist';

    /** Channels with an automatic delete (everything else in the registry is manual). */
    private const AUTO_EBAY = ['ebay' => 'Ebay 1', 'ebaytwo' => 'Ebay 2', 'ebaythree' => 'Ebay 3'];

    /** @var array<string, string> */
    private static array $queue = [];

    private static bool $hooked = false;

    /**
     * Queue a SKU; the whole batch is processed once, after the response is sent.
     */
    public static function queue(string $sku): void
    {
        $sku = trim($sku);
        if ($sku === '') {
            return;
        }
        self::$queue[strtolower($sku)] = $sku;

        if (self::$hooked) {
            return;
        }
        self::$hooked = true;
        app()->terminating(function () {
            $skus = array_values(self::$queue);
            self::$queue = [];
            self::$hooked = false;
            try {
                app(self::class)->processBatch($skus);
            } catch (\Throwable $e) {
                Log::error('DC marketplace delist batch failed', ['error' => $e->getMessage(), 'skus' => $skus]);
            }
        });
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, array{deleted: list<string>, failed: array<string, string>, manual: list<string>}>
     */
    public function processBatch(array $skus): array
    {
        @set_time_limit(0);
        $skus = array_values(array_unique(array_filter(array_map('trim', $skus))));
        if ($skus === []) {
            return [];
        }

        $results = [];
        foreach ($skus as $sku) {
            $results[$sku] = ['deleted' => [], 'failed' => [], 'manual' => []];
        }

        // Where each SKU is listed according to the app (one lookup per channel for the whole batch)
        $listedByChannel = [];
        foreach (ChannelListingRegistry::all() as $key => $cfg) {
            if ($key === 'shopifyb2c') {
                continue; // handled by the Shopify archive below
            }
            try {
                $listedByChannel[$key] = ChannelListingRegistry::loadListedIds($cfg, $skus);
            } catch (\Throwable $e) {
                Log::warning('DC delist: could not check listings for '.$key, ['error' => $e->getMessage()]);
                $listedByChannel[$key] = null; // unknown
            }
        }

        $shopify = app(ShopifyProductArchiveService::class);
        $amazon = null;
        try {
            $amazon = app(AmazonSpApiService::class);
        } catch (\Throwable $e) {
            $amazon = null;
        }

        foreach ($skus as $sku) {
            // Shopify (main store)
            $r = $shopify->archive($sku);
            if (! $r['ok']) {
                $results[$sku]['failed']['Shopify'] = $r['message'];
            } elseif (! empty($r['blocked'])) {
                $results[$sku]['failed']['Shopify'] = $r['message'];
            } elseif (! $r['skipped']) {
                $results[$sku]['deleted'][] = 'Shopify';
            }

            // Amazon
            if ($amazon !== null && $amazon->isConfigured()) {
                $a = $amazon->deleteListingsItem($sku);
                if (! $a['success']) {
                    $results[$sku]['failed']['Amazon'] = $a['message'];
                } elseif (empty($a['not_found'])) {
                    $results[$sku]['deleted'][] = 'Amazon';
                }
            } else {
                $results[$sku]['manual'][] = 'Amazon (not connected)';
            }

            // Registry channels
            foreach ($listedByChannel as $key => $map) {
                $label = $this->label($key);
                if ($map === null) {
                    $results[$sku]['failed'][$label] = 'Could not check whether the SKU is listed here.';

                    continue;
                }
                $id = ListingCountsEngine::listingIdFromMap($map, $sku);
                if ($id === '' || ListingCountsEngine::isPageCreatedId($id)) {
                    continue; // not live on this marketplace
                }

                if (isset(self::AUTO_EBAY[$key])) {
                    $e = ListingManagerEbayTradingPublisher::endItem($key, $id);
                    if ($e['success'] || preg_match('/already (been )?(closed|ended)|has been closed|not (found|active)/i', (string) $e['message'])) {
                        $results[$sku]['deleted'][] = $label;
                    } else {
                        $results[$sku]['failed'][$label] = $e['message'];
                    }

                    continue;
                }

                $results[$sku]['manual'][] = $label;
            }
        }

        $this->assignTask($results);

        Log::info('DC marketplace delist done', ['skus' => $skus, 'results' => $results]);

        return $results;
    }

    /**
     * @param  array<string, array{deleted: list<string>, failed: array<string, string>, manual: list<string>}>  $results
     */
    private function assignTask(array $results): void
    {
        $lines = [];
        $flagged = [];
        foreach ($results as $sku => $r) {
            if ($r['failed'] === [] && $r['manual'] === []) {
                continue;
            }
            $flagged[] = $sku;
            $line = $sku;
            if ($r['failed'] !== []) {
                $parts = [];
                foreach ($r['failed'] as $label => $msg) {
                    $parts[] = $label.' ('.mb_substr(trim((string) $msg), 0, 160).')';
                }
                $line .= "\n   FAILED (delete by hand): ".implode('; ', $parts);
            }
            if ($r['manual'] !== []) {
                $line .= "\n   NOT CONNECTED (delete by hand): ".implode(', ', array_unique($r['manual']));
            }
            if ($r['deleted'] !== []) {
                $line .= "\n   Already deleted: ".implode(', ', array_unique($r['deleted']));
            }
            $lines[] = $line;
        }

        if ($flagged === []) {
            return;
        }

        $total = count($results);
        $title = 'DC products: delete from marketplaces ('.count($flagged).' SKU'.(count($flagged) === 1 ? '' : 's').')';
        $description = 'These SKUs were marked DC and archived. The app deleted them from the connected marketplaces it could. '
            ."Delete the SKUs below from the marketplaces listed as FAILED or NOT CONNECTED.\n\n"
            .'SKUs: '.implode(', ', $flagged)."\n\n"
            .implode("\n\n", $lines);
        if ($total > count($flagged)) {
            $description .= "\n\n".($total - count($flagged)).' other DC SKU(s) in this batch were fully deleted automatically.';
        }
        $description = mb_substr($description, 0, 60000);

        try {
            $start = now();
            $due = $start->copy()->addDays(2);
            $data = [
                'title' => $title,
                'description' => $description,
                'group' => self::GROUP,
                'priority' => 'High',
                'assignor' => self::ASSIGNOR,
                'assign_to' => self::ASSIGNEE,
                'status' => 'Todo',
                'eta_time' => max(30, 5 * count($flagged)),
                'start_date' => $start,
                'completion_date' => $due,
                'due_date' => $due,
                'completion_day' => 0,
                'etc_done' => 0,
                'is_missed' => 0,
                'is_missed_track' => 0,
                'workspace' => 0,
                'order' => 0,
                'task_id' => '',
                'link1' => url('/product-master'),
                'link2' => '', 'link3' => '', 'link4' => '', 'link5' => '', 'link6' => '', 'link7' => '',
                'link8' => 'dc-delist-'.md5(implode('|', $flagged).$start->timestamp),
                'link9' => '',
                'is_data_from' => 0,
                'is_automate_task' => 0,
                'task_type' => 'manual',
                'rework_reason' => '',
                'delete_rating' => 0,
                'delete_feedback' => '',
                'split_tasks' => 0,
            ];
            if (Schema::hasColumn('tasks', 'is_corrective_action')) {
                $data['is_corrective_action'] = 0;
            }

            $task = Task::create($data);

            try {
                app(TaskWhatsAppNotificationService::class)->notifyNewTaskAssigned($task);
            } catch (\Throwable $e) {
                Log::warning('DC delist task WhatsApp failed', ['task_id' => $task->id, 'error' => $e->getMessage()]);
            }
        } catch (\Throwable $e) {
            Log::error('DC delist task could not be created', ['error' => $e->getMessage(), 'skus' => $flagged]);
        }
    }

    private function label(string $key): string
    {
        if (isset(self::AUTO_EBAY[$key])) {
            return self::AUTO_EBAY[$key];
        }
        $labels = [
            'ebayvariation' => 'Ebay Variation', 'doba' => 'Doba', 'walmart' => 'Walmart',
            'neweggb2c' => 'Newegg B2C', 'neweggb2b' => 'Newegg B2B', 'tiktokshop' => 'TikTok Shop',
            'tiktokshop2' => 'TikTok Shop 2', 'reverb' => 'Reverb', 'shein' => 'Shein',
            'aliexpress' => 'AliExpress', 'temu' => 'Temu', 'temu2' => 'Temu 2', 'temu3' => 'Temu 3',
            'macys' => "Macy's", 'wayfair' => 'Wayfair', 'pls' => 'PLS', 'bestbuyusa' => 'Best Buy USA',
            'faire' => 'Faire', 'fbmarketplace' => 'Facebook Marketplace', 'fbshop' => 'Facebook Shop',
            'instagramshop' => 'Instagram Shop', 'shopifywholesale' => 'Shopify Wholesale',
            'mercariwoship' => 'Mercari (w/o ship)', 'mercariwship' => 'Mercari (w ship)', 'autods' => 'AutoDS',
            'poshmark' => 'Poshmark', 'spocket' => 'Spocket', 'zendrop' => 'Zendrop', 'syncee' => 'Syncee',
            'offerup' => 'OfferUp', 'appscenic' => 'Appscenic', 'yamibuy' => 'Yamibuy',
            'swgearexchange' => 'SW Gear Exchange', 'business5core' => 'Business 5Core', 'topdawg' => 'TopDawg',
            'purchasingpower' => 'Purchasing Power', 'alibaba' => 'Alibaba', 'b5cb2b' => 'Business 5Core B2B',
            'cjdropshipping' => 'CJ Dropshipping', 'depop' => 'Depop', 'vinted' => 'Vinted',
            'dhgate' => 'DHgate', 'tiendamia' => 'Tiendamia',
        ];

        return $labels[$key] ?? ucfirst($key);
    }
}
