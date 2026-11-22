<?php

namespace App\Services;

use App\Models\AmazonListingStatus;
use App\Models\AliexpressListingStatus;
use App\Models\BestbuyUSAListingStatus;
use App\Models\EbayListingStatus;
use App\Models\EbayThreeListingStatus;
use App\Models\EbayTwoListingStatus;
use App\Models\InstagramShopListingStatus;
use App\Models\MacysListingStatus;
use App\Models\NeweggB2CListingStatus;
use App\Models\PlsListingStatus;
use App\Models\ProductMaster;
use App\Models\PurchasingPowerListingStatus;
use App\Models\ReverbListingStatus;
use App\Models\SheinListingStatus;
use App\Models\Temu2ListingStatus;
use App\Models\Temu3ListingStatus;
use App\Models\TemuListingStatus;
use App\Models\TiktokShopListingStatus;
use App\Models\TiktokTwoShopListingStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * /pricing-by-channel: one row per SKU, and for every active B2C channel of channel_master its price,
 * NPFT %, NROI %, unique listing id and buyer link.
 *
 * Price / NPFT / NROI come from PricingErrorsFixCvrCacheBuilder (the same numbers /pricing-errors-fix shows,
 * which in turn are the /price-increase numbers), so this page never disagrees with them.
 */
class PricingByChannelService
{
    public const CACHE_KEY = 'pricing_by_channel_rows_v1';

    private const CACHE_SECONDS = 180;

    /** channel_master name (lowercase, letters and digits only) => PEF channel key. */
    private const NAME_TO_KEY = [
        'amazon' => 'amazon',
        'ebay' => 'ebay1',
        'ebayone' => 'ebay1',
        'ebaytwo' => 'ebay2',
        'ebay2' => 'ebay2',
        'ebaythree' => 'ebay3',
        'ebay3' => 'ebay3',
        'reverb' => 'reverb',
        'macys' => 'macy',
        'macy' => 'macy',
        'pls' => 'pls',
        'temu' => 'temu',
        'temu2' => 'temu2',
        'temu3' => 'temu3',
        'instagramshop' => 'instagram',
        'tiktokshop' => 'tiktok',
        'tiktok1' => 'tiktok',
        'tiktok2' => 'tiktok2',
        'bestbuyusa' => 'bestbuy',
        'bestbuy' => 'bestbuy',
        'shein' => 'shein',
        'aliexpress' => 'aliexpress',
        'purchasingpower' => 'ppower',
        'newegg' => 'newegg',
    ];

    private const LABELS = [
        'amazon' => 'Amazon', 'ebay1' => 'eBay 1', 'ebay2' => 'eBay 2', 'ebay3' => 'eBay 3',
        'reverb' => 'Reverb', 'macy' => "Macy's", 'pls' => 'PLS', 'temu' => 'Temu', 'temu2' => 'Temu 2',
        'temu3' => 'Temu 3', 'instagram' => 'Instagram Shop', 'tiktok' => 'TikTok 1', 'tiktok2' => 'TikTok 2',
        'bestbuy' => 'Best Buy', 'shein' => 'Shein', 'aliexpress' => 'AliExpress',
        'ppower' => 'Purchasing Power', 'newegg' => 'Newegg',
    ];

    /** Channels with no marketplace ads: NPFT = GPFT, NROI = GROI (same list as the PEF builder). */
    private const NO_ADS = ['ppower', 'shein', 'temu3', 'pls', 'newegg'];

    /**
     * Channels /cvr-master-save-suggested-data can store a suggested price (SPRICE) for. Temu / Temu 2 are left out:
     * their price is worked out from the base price with their own rules (use /new-temuone, /new-temu2); PLS,
     * Instagram, Newegg and Temu 3 have no suggested-price store behind that endpoint.
     */
    private const CAN_SAVE = [
        'amazon', 'ebay1', 'ebay2', 'ebay3', 'tiktok', 'tiktok2', 'bestbuy', 'macy',
        'reverb', 'shein', 'aliexpress', 'ppower',
    ];

    /** Channels /cvr-master-push-price pushes a plain price to. Push always saves the price first, so it needs CAN_SAVE too. */
    private const CAN_PUSH = [
        'amazon', 'ebay1', 'ebay2', 'ebay3', 'tiktok', 'tiktok2', 'bestbuy', 'macy',
        'reverb', 'ppower',
    ];

    /** key => [table, id column, fallback id column]. Channels not listed take the id from the buyer link. */
    private const ID_SOURCES = [
        'amazon' => ['amazon_datsheets', 'asin', null],
        'ebay1' => ['ebay_metrics', 'item_id', null],
        'ebay2' => ['ebay_2_metrics', 'item_id', null],
        'ebay3' => ['ebay_3_metrics', 'item_id', null],
        'reverb' => ['reverb_products', 'reverb_listing_id', null],
        'temu' => ['temu_metrics', 'goods_id', null],
        'temu2' => ['temu2_metrics', 'goods_id', null],
        'temu3' => ['temu3_metrics', 'goods_id', null],
        'tiktok' => ['tiktok_products', 'product_id', null],
        'tiktok2' => ['tiktok_products_two', 'product_id', null],
        'shein' => ['shein_metrics', 'spu_name', 'shein_sku_code'],
        'ppower' => ['purchasing_power_metrics', 'product_id', null],
        'newegg' => ['newegg_metrics', 'product_id', null],
    ];

    /** key => listing-status model whose value JSON holds buyer_link. */
    private const LINK_MODELS = [
        'amazon' => AmazonListingStatus::class,
        'ebay1' => EbayListingStatus::class,
        'ebay2' => EbayTwoListingStatus::class,
        'ebay3' => EbayThreeListingStatus::class,
        'reverb' => ReverbListingStatus::class,
        'macy' => MacysListingStatus::class,
        'pls' => PlsListingStatus::class,
        'temu' => TemuListingStatus::class,
        'temu2' => Temu2ListingStatus::class,
        'temu3' => Temu3ListingStatus::class,
        'instagram' => InstagramShopListingStatus::class,
        'tiktok' => TiktokShopListingStatus::class,
        'tiktok2' => TiktokTwoShopListingStatus::class,
        'bestbuy' => BestbuyUSAListingStatus::class,
        'shein' => SheinListingStatus::class,
        'aliexpress' => AliexpressListingStatus::class,
        'ppower' => PurchasingPowerListingStatus::class,
        'newegg' => NeweggB2CListingStatus::class,
    ];

    /**
     * Active B2C channels of channel_master, in channel_master order.
     *
     * @return list<array{key: string, label: string, can_save: bool, can_push: bool, no_ads: bool}>
     */
    public function channels(): array
    {
        $out = [];
        try {
            $names = DB::table('channel_master')
                ->whereRaw("UPPER(TRIM(type)) = 'B2C'")
                ->whereRaw("LOWER(TRIM(status)) = 'active'")
                ->orderBy('id')
                ->pluck('channel');
        } catch (\Throwable $e) {
            Log::warning('PricingByChannel channel_master read failed: '.$e->getMessage());

            return [];
        }
        foreach ($names as $name) {
            $norm = preg_replace('/[^a-z0-9]+/', '', strtolower((string) $name));
            $key = self::NAME_TO_KEY[$norm] ?? null;
            if ($key === null || isset($out[$key])) {
                continue;
            }
            $out[$key] = [
                'key' => $key,
                'label' => self::LABELS[$key] ?? (string) $name,
                'can_save' => in_array($key, self::CAN_SAVE, true),
                'can_push' => in_array($key, self::CAN_PUSH, true),
                'no_ads' => in_array($key, self::NO_ADS, true),
            ];
        }

        return array_values($out);
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{channels: list<array<string, mixed>>, rows: list<array<string, mixed>>, errors: array<string, string>}
     */
    public function build(bool $fresh = false): array
    {
        if ($fresh) {
            self::forget();
        }
        try {
            $cached = Cache::get(self::CACHE_KEY);
            if (is_array($cached) && isset($cached['rows'])) {
                return $cached;
            }
        } catch (\Throwable $e) {
            // cache unavailable: compute
        }

        $channels = $this->channels();
        $keys = array_column($channels, 'key');
        // PEF's own channel keys: eBay 1 is "ebay" there.
        $wanted = array_map(static fn ($k) => $k === 'ebay1' ? 'ebay' : $k, $keys);
        $built = app(PricingErrorsFixCvrCacheBuilder::class)->build($wanted, null, false);

        $bySku = [];
        foreach ($built['rows'] as $r) {
            $sku = (string) ($r['sku'] ?? '');
            if ($sku === '') {
                continue;
            }
            $bySku[$sku]['_base'] ??= $r;
            $bySku[$sku]['channels'][(string) $r['channel_key']] = $r;
        }

        $skuList = array_keys($bySku);
        $pm = $this->productMasterBasics($skuList);
        $ids = $this->loadIds($keys);
        $links = $this->loadLinks($keys);

        $key = static fn ($s) => self::skuKey($s);
        $rows = [];
        foreach ($bySku as $sku => $info) {
            $base = $info['_base'];
            $k = $key($sku);
            $row = [
                'sku' => $sku,
                'parent' => $base['parent'] ?? null,
                'image_path' => $pm[$k]['image'] ?? ($base['image_path'] ?? null),
                'status' => $pm[$k]['status'] ?? '',
                'upc' => $pm[$k]['upc'] ?? '',
                'inv' => $base['inv'] ?? 0,
                'channels' => [],
            ];
            foreach ($channels as $ch) {
                $ck = $ch['key'];
                $c = $info['channels'][$ck] ?? null;
                $link = $links[$ck][$k] ?? '';
                $id = $ids[$ck][$k] ?? '';
                if ($link === '') {
                    $link = $this->builtLink($ck, $id);
                }
                if ($id === '' && ! isset(self::ID_SOURCES[$ck]) && $link !== '') {
                    $id = $this->idFromLink($link);
                }
                $price = $c && ($c['price'] ?? 0) > 0 ? (float) $c['price'] : null;
                $row['prc_'.$ck] = $price;
                $row['npft_'.$ck] = $price !== null ? ($c['npft'] ?? null) : null;
                $row['nroi_'.$ck] = $price !== null ? ($c['nroi'] ?? null) : null;
                $row['id_'.$ck] = $id;
                $row['link_'.$ck] = $link;
                // What the edit modal needs beyond the flat columns: the saved suggested price and the cost inputs
                // for the live NPFT % / NROI % preview. Only for channels the SKU is priced on.
                if ($c !== null) {
                    $row['channels'][$ck] = [
                        'sprice' => $c['sprice'] ?? null,
                        'lp' => $c['lp'] ?? 0,
                        'ship' => $c['ship'] ?? 0,
                        'margin' => $c['margin'] ?? 0,
                        'ads_pct' => $c['ads_pct'] ?? 0,
                    ];
                }
            }
            $rows[] = $row;
        }

        $out = ['channels' => $channels, 'rows' => $rows, 'errors' => $built['errors'] ?? []];
        if ($rows !== []) {
            try {
                Cache::put(self::CACHE_KEY, $out, now()->addSeconds(self::CACHE_SECONDS));
            } catch (\Throwable $e) {
                // value is still correct for this request
            }
        }

        return $out;
    }

    /** SKU match key: uppercase, NBSP folded, every whitespace run removed. */
    public static function skuKey($sku): string
    {
        return preg_replace('/\s+/u', '', strtoupper(str_replace(["\xC2\xA0", "\xE2\x80\xAF", "\xA0"], ' ', trim((string) $sku)))) ?? '';
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, array{status: string, upc: string, image: ?string}>
     */
    private function productMasterBasics(array $skus): array
    {
        $out = [];
        try {
            foreach (array_chunk($skus, 500) as $chunk) {
                foreach (ProductMaster::query()->whereIn('sku', $chunk)->get(['sku', 'Values']) as $pm) {
                    $v = is_array($pm->Values) ? $pm->Values : (json_decode((string) $pm->Values, true) ?: []);
                    $out[self::skuKey($pm->sku)] = [
                        'status' => trim((string) ($v['status'] ?? '')),
                        'upc' => trim((string) ($v['upc'] ?? '')),
                        'image' => ! empty($v['image_path']) ? (string) $v['image_path'] : null,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('PricingByChannel product master read failed: '.$e->getMessage());
        }

        return $out;
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, array<string, string>> channel key => sku key => id
     */
    private function loadIds(array $keys): array
    {
        $out = [];
        foreach ($keys as $ck) {
            $src = self::ID_SOURCES[$ck] ?? null;
            if ($src === null) {
                continue;
            }
            [$table, $col, $fallback] = $src;
            $out[$ck] = [];
            try {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $col)) {
                    continue;
                }
                $cols = ['sku', $col];
                if ($fallback !== null && Schema::hasColumn($table, $fallback)) {
                    $cols[] = $fallback;
                }
                foreach (DB::table($table)->whereNotNull('sku')->get($cols) as $r) {
                    $id = trim((string) ($r->{$col} ?? ''));
                    if ($id === '' && $fallback !== null) {
                        $id = trim((string) ($r->{$fallback} ?? ''));
                    }
                    $k = self::skuKey($r->sku);
                    if ($k !== '' && $id !== '' && ! isset($out[$ck][$k])) {
                        $out[$ck][$k] = $id;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('PricingByChannel id read failed ('.$table.'): '.$e->getMessage());
            }
        }

        return $out;
    }

    /**
     * Buyer links saved in each channel's listing-status table.
     *
     * @param  list<string>  $keys
     * @return array<string, array<string, string>> channel key => sku key => url
     */
    private function loadLinks(array $keys): array
    {
        $out = [];
        foreach ($keys as $ck) {
            $model = self::LINK_MODELS[$ck] ?? null;
            if ($model === null) {
                continue;
            }
            $out[$ck] = [];
            try {
                $table = (new $model)->getTable();
                if (! Schema::hasTable($table)) {
                    continue;
                }
                foreach ($model::query()->whereNotNull('sku')->get(['sku', 'value']) as $r) {
                    $v = is_array($r->value) ? $r->value : (json_decode((string) $r->value, true) ?: []);
                    $link = trim((string) ($v['buyer_link'] ?? ''));
                    $k = self::skuKey($r->sku);
                    if ($k !== '' && preg_match('#^https?://#i', $link) === 1 && ! isset($out[$ck][$k])) {
                        $out[$ck][$k] = $link;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('PricingByChannel link read failed ('.$ck.'): '.$e->getMessage());
            }
        }

        return $out;
    }

    /** Buyer link rebuilt from the id (used only when no buyer link is saved), where the channel has a public URL by id. */
    private function builtLink(string $key, string $id): string
    {
        if ($id === '') {
            return '';
        }

        return match ($key) {
            'amazon' => 'https://www.amazon.com/dp/'.rawurlencode($id),
            'ebay1', 'ebay2', 'ebay3' => 'https://www.ebay.com/itm/'.rawurlencode($id),
            'reverb' => 'https://reverb.com/item/'.rawurlencode($id),
            'temu', 'temu2', 'temu3' => 'https://www.temu.com/goods.html?goods_id='.rawurlencode($id),
            'tiktok', 'tiktok2' => 'https://www.tiktok.com/view/product/'.rawurlencode($id),
            default => '',
        };
    }

    /** Listing id out of a buyer link, for channels with no id table. */
    private function idFromLink(string $link): string
    {
        $patterns = [
            '#/(?:dp|gp/product)/([A-Z0-9]{10})(?:[/?]|$)#i',
            '#/itm/(?:[^/]+/)?(\d{9,})#',
            '#[?&](?:goods_id|product_id|item_id|id|ID)=([A-Za-z0-9_-]+)#',
            '#-g-(\d+)#',
            '#/(?:product|item|p|ip)/(?:[^/?]+/)*?(\d{5,})#',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $link, $m) === 1) {
                return $m[1];
            }
        }
        if (preg_match_all('/\d{6,}/', (string) parse_url($link, PHP_URL_PATH), $m) && $m[0] !== []) {
            return end($m[0]);
        }

        return '';
    }
}
