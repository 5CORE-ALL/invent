<?php

namespace App\Services\MarketplaceManager;

use App\Models\AliexpressMetric;
use App\Models\AliexpressOrderMetric;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AliexpressDetailFormatter
{
    /**
     * @param  array<string, mixed>|null  $aeLive
     * @param  array<int, array<string, mixed>>  $aeSkuRows
     * @return array<string, mixed>
     */
    public function formatProduct(?array $aeLive, ?AliexpressMetric $metric, ShopifySku $shopify, array $aeSkuRows = []): array
    {
        $ae = $this->arr($aeLive);
        $shopifyQty = $shopify->available_to_sell ?? $shopify->inv ?? $shopify->on_hand ?? null;
        $shopifyPrice = $shopify->b2c_price ?? $shopify->price ?? null;

        $shopifyCatalog = $this->loadShopifyCatalogRow($shopify);
        $shopifyImages = $this->extractShopifyImages($shopify, $shopifyCatalog);
        $aeImages = $this->extractProductImages($ae, $aeSkuRows);
        $shopifyDescription = $this->resolveShopifyDescription($shopify, $shopifyCatalog);
        $descriptions = $this->extractProductDescriptions($ae);
        $variants = $this->formatProductVariants($ae, $aeSkuRows, $metric, $shopify);
        $properties = $this->extractProductProperties($ae);
        $shopifyProperties = $this->extractShopifyProperties($shopify, $shopifyCatalog);

        $cachedPrice = $this->money($metric?->price);
        $minPrice = $this->money($ae['product_min_price'] ?? null) ?? $cachedPrice;
        $maxPrice = $this->money($ae['product_max_price'] ?? null) ?? $cachedPrice;

        return [
            'shopify' => [
                'sku' => $shopify->sku,
                'product_title' => $shopify->product_title,
                'variant_title' => $shopify->variant_title,
                'variant_id' => $shopify->variant_id,
                'product_link' => $shopify->product_link,
                'image' => $shopify->image_src,
                'available_to_sell' => $shopifyQty,
                'on_hand' => $shopify->on_hand,
                'committed' => $shopify->committed,
                'incoming' => $shopify->incoming,
                'unavailable' => $shopify->unavailable,
                'b2c_price' => $shopifyPrice,
                'b2b_price' => $shopify->b2b_price,
                'price' => $shopify->price,
                'shopify_l30' => $shopify->shopify_l30,
                'images' => $shopifyImages,
                'main_image' => $shopifyImages[0] ?? $shopify->image_src,
                'description_html' => $shopifyDescription['html'],
                'description_source' => $shopifyDescription['source'],
                'properties' => $shopifyProperties,
                'catalog_store' => $shopifyCatalog?->store ?? null,
                'shopify_product_id' => $shopifyCatalog?->shopify_product_id
                    ? (string) $shopifyCatalog->shopify_product_id
                    : null,
                'vendor' => $this->str($shopifyCatalog?->vendor ?? null),
                'product_type' => $this->str($shopifyCatalog?->product_type ?? null),
                'handle' => $this->str($shopifyCatalog?->handle ?? null),
                'catalog_status' => $this->str($shopifyCatalog?->status ?? null),
            ],
            'link' => [
                'product_id' => $metric?->product_id,
                'title' => $metric?->product_name,
                'price' => $metric?->price,
                'l30' => $metric?->l30,
                'l60' => $metric?->l60,
                'last_order_date' => $metric?->last_order_date,
                'bullet_points' => $metric?->bullet_points,
            ],
            'aliexpress' => [
                'product_id' => $this->str($ae['product_id'] ?? $metric?->product_id),
                'title' => $this->extractProductTitle($ae) ?? $metric?->product_name,
                'status' => $this->str($ae['product_status_type'] ?? $ae['status'] ?? $ae['product_status'] ?? null),
                'category_id' => $this->str($ae['category_id'] ?? $ae['categoryId'] ?? null),
                'currency' => $this->str($ae['currency_code'] ?? $ae['currency'] ?? null),
                'unit' => $this->str($ae['product_unit'] ?? null),
                'package_type' => $this->str($ae['package_type'] ?? null),
                'bulk_order' => $ae['bulk_order'] ?? null,
                'bulk_discount' => $ae['bulk_discount'] ?? null,
                'freight_template_id' => $this->str($ae['freight_template_id'] ?? null),
                'gmt_create' => $this->str($ae['gmt_create'] ?? $ae['create_time'] ?? null),
                'gmt_modified' => $this->str($ae['gmt_modified'] ?? $ae['modified_time'] ?? null),
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'cached_price' => $cachedPrice,
                'images' => $aeImages,
                'main_image' => $aeImages[0] ?? null,
                'descriptions' => $descriptions,
                'variants' => $variants,
                'properties' => $properties,
                'subjects' => $this->extractMultiLanguageSubjects($ae),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $orderRoot
     * @param  Collection<int, AliexpressOrderMetric>  $lines
     * @return array<string, mixed>
     */
    public function formatOrder(array $orderRoot, Collection $lines, AliexpressOrderMetric $primaryLine): array
    {
        $order = $this->arr($orderRoot);
        $buyer = $this->arr($order['buyer_info'] ?? []);
        $addr = $this->arr($order['receipt_address'] ?? []);
        $amounts = $this->extractOrderAmounts($order, $lines);
        $logistics = $this->extractLogisticsList($order);
        $apiLines = $this->extractRichOrderLines($order, $lines);

        return [
            'summary' => [
                'order_id' => (string) ($order['order_id'] ?? $order['id'] ?? $primaryLine->order_id),
                'order_number' => $order['order_number'] ?? $primaryLine->order_number ?? null,
                'status' => $order['order_status'] ?? $order['status'] ?? $primaryLine->status,
                'buyer_remark' => $order['buyer_remark'] ?? $order['memo'] ?? null,
                'seller_remark' => $order['seller_remark'] ?? null,
                'created' => $order['gmt_create'] ?? $order['create_time'] ?? $primaryLine->order_date,
                'paid' => $order['gmt_pay_time'] ?? $order['pay_time'] ?? null,
                'sent' => $order['gmt_send_goods_time'] ?? null,
                'finished' => $order['gmt_receive_goods_time'] ?? $order['end_time'] ?? null,
                'modified' => $order['gmt_modified'] ?? null,
            ],
            'amounts' => $amounts,
            'buyer' => [
                'name' => $buyer['first_name'] ?? $addr['contact_person'] ?? $order['buyer_signer_fullname'] ?? null,
                'last_name' => $buyer['last_name'] ?? null,
                'login_id' => $buyer['login_id'] ?? $order['buyer_login_id'] ?? null,
                'email' => $buyer['email'] ?? $addr['email'] ?? null,
                'phone' => $addr['mobile_no'] ?? $addr['phone_number'] ?? $addr['phone'] ?? null,
                'country' => $buyer['country'] ?? $addr['country'] ?? null,
            ],
            'shipping' => [
                'recipient' => $addr['contact_person'] ?? $addr['receiver'] ?? null,
                'address_line_1' => $addr['address'] ?? $addr['detail_address'] ?? null,
                'address_line_2' => $addr['address2'] ?? null,
                'city' => $addr['city'] ?? null,
                'province' => $addr['province'] ?? $addr['state'] ?? null,
                'zip' => $addr['zip'] ?? $addr['zip_code'] ?? null,
                'country' => $addr['country'] ?? null,
                'full_address' => $this->joinAddress($addr),
            ],
            'logistics' => $logistics,
            'line_items' => $apiLines,
            'shopify' => [
                'shopify_order_id' => $primaryLine->shopify_order_id,
                'import_status' => $primaryLine->import_status,
                'pushed_to_shopify_at' => $primaryLine->pushed_to_shopify_at,
            ],
            'payment' => [
                'method' => $order['payment_type'] ?? $order['pay_type'] ?? null,
                'currency' => $this->moneyCurrency($order['order_amount'] ?? $order['pay_amount'] ?? null),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $ae
     * @param  array<int, array<string, mixed>>  $aeSkuRows
     * @return array<int, string>
     */
    protected function extractProductImages(array $ae, array $aeSkuRows): array
    {
        $urls = [];

        foreach ([
            $ae['main_image_url'] ?? null,
            $ae['product_main_image'] ?? null,
            $ae['image_url'] ?? null,
        ] as $url) {
            $urls = array_merge($urls, $this->splitImageUrls($url));
        }

        $urls = array_merge($urls, $this->splitImageUrls($ae['image_u_r_ls'] ?? $ae['image_urls'] ?? null));

        foreach (['aeop_a_e_product_propertys', 'aeop_ae_product_propertys', 'product_properties'] as $key) {
            foreach ($this->list($ae[$key] ?? []) as $prop) {
                $prop = $this->arr($prop);
                if (($prop['attr_name'] ?? '') === 'image' || isset($prop['attr_value'])) {
                    $urls = array_merge($urls, $this->splitImageUrls($prop['attr_value'] ?? $prop['attr_value_id'] ?? null));
                }
            }
        }

        foreach ($aeSkuRows as $row) {
            $urls = array_merge($urls, $this->splitImageUrls($row['image'] ?? $row['sku_image'] ?? null));
        }

        return array_values(array_unique(array_filter($urls)));
    }

    /**
     * @return array{html: ?string, source: ?string}
     */
    protected function resolveShopifyDescription(ShopifySku $shopify, ?object $catalogRow): array
    {
        $bodyHtml = trim((string) ($catalogRow->body_html ?? ''));
        if ($bodyHtml !== '') {
            return ['html' => $bodyHtml, 'source' => 'shopify_catalog'];
        }

        $pmHtml = $this->resolveProductMasterDescriptionHtml($shopify->sku);
        if ($pmHtml !== null) {
            return ['html' => $pmHtml, 'source' => 'product_master'];
        }

        return ['html' => null, 'source' => null];
    }

    protected function resolveProductMasterDescriptionHtml(?string $sku): ?string
    {
        if ($sku === null || trim($sku) === '') {
            return null;
        }

        $pm = ProductMaster::query()
            ->whereRaw('LOWER(TRIM(sku)) = ?', [mb_strtolower(trim($sku))])
            ->first();

        if (! $pm) {
            return null;
        }

        $html = trim((string) ($pm->description_html ?? ''));
        if ($html !== '') {
            return $html;
        }

        foreach (['description_1500', 'description_1000', 'description_800', 'description_600', 'product_description'] as $col) {
            $text = trim((string) ($pm->{$col} ?? ''));
            if ($text !== '') {
                return '<p>'.nl2br(e($text), false).'</p>';
            }
        }

        return null;
    }

    /**
     * @return array<int, array{name: string, value: string}>
     */
    protected function extractShopifyProperties(ShopifySku $shopify, ?object $catalogRow): array
    {
        $out = [];

        foreach ([
            'Vendor' => $this->str($catalogRow?->vendor ?? null),
            'Product type' => $this->str($catalogRow?->product_type ?? null),
            'Catalog status' => $this->str($catalogRow?->status ?? null),
            'Handle' => $this->str($catalogRow?->handle ?? null),
            'Store' => $this->str($catalogRow?->store ?? null),
        ] as $name => $value) {
            if ($value) {
                $out[] = ['name' => $name, 'value' => $value];
            }
        }

        $pm = ProductMaster::query()
            ->whereRaw('LOWER(TRIM(sku)) = ?', [mb_strtolower(trim((string) $shopify->sku))])
            ->first();

        if ($pm) {
            foreach ([
                'Category' => $this->str($pm->category ?? null),
                'Group' => $this->str($pm->group ?? null),
            ] as $name => $value) {
                if ($value) {
                    $out[] = ['name' => $name, 'value' => $value];
                }
            }
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    protected function extractShopifyImages(ShopifySku $shopify, ?object $catalogRow): array
    {
        $urls = $this->parseCatalogImageUrls($catalogRow);

        if ($shopify->image_src) {
            array_unshift($urls, $shopify->image_src);
        }

        if ($urls === []) {
            $pm = ProductMaster::query()
                ->whereRaw('LOWER(TRIM(sku)) = ?', [mb_strtolower(trim((string) $shopify->sku))])
                ->first();

            if ($pm) {
                foreach (array_merge(
                    [$pm->main_image ?? null, $pm->main_image_brand ?? null],
                    array_map(fn ($i) => $pm->{"image{$i}"} ?? null, range(1, 12))
                ) as $url) {
                    $url = trim((string) $url);
                    if ($url !== '') {
                        $urls[] = $url;
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($urls)));
    }

    /**
     * @return array<int, string>
     */
    protected function parseCatalogImageUrls(?object $catalogRow): array
    {
        if (! $catalogRow) {
            return [];
        }

        $urls = [];

        if (Schema::hasColumn('shopify_catalog_products', 'image_urls')) {
            $decoded = json_decode((string) ($catalogRow->image_urls ?? ''), true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (is_string($item) && trim($item) !== '') {
                        $urls[] = trim($item);
                    } elseif (is_array($item) && ! empty($item['src'])) {
                        $urls[] = trim((string) $item['src']);
                    }
                }
            }
        }

        if ($urls === [] && Schema::hasColumn('shopify_catalog_products', 'images')) {
            $decoded = json_decode((string) ($catalogRow->images ?? ''), true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (is_string($item) && trim($item) !== '') {
                        $urls[] = trim($item);
                    } elseif (is_array($item) && ! empty($item['src'])) {
                        $urls[] = trim((string) $item['src']);
                    }
                }
            }
        }

        if ($urls === [] && ! empty($catalogRow->image_src)) {
            $urls[] = trim((string) $catalogRow->image_src);
        }

        return array_values(array_unique(array_filter($urls)));
    }

    protected function loadShopifyCatalogRow(ShopifySku $shopify): ?object
    {
        if (! Schema::hasTable('shopify_catalog_variants') || ! Schema::hasTable('shopify_catalog_products')) {
            return null;
        }

        $select = [
            'p.id',
            'p.title',
            'p.handle',
            'p.status',
            'p.body_html',
            'p.vendor',
            'p.product_type',
            'v.store',
            'v.shopify_variant_id',
            'v.shopify_product_id',
        ];

        if (Schema::hasColumn('shopify_catalog_products', 'image_src')) {
            $select[] = 'p.image_src';
        }
        if (Schema::hasColumn('shopify_catalog_products', 'images')) {
            $select[] = 'p.images';
        }
        if (Schema::hasColumn('shopify_catalog_products', 'image_urls')) {
            $select[] = 'p.image_urls';
        }

        $base = DB::table('shopify_catalog_variants as v')
            ->join('shopify_catalog_products as p', 'p.id', '=', 'v.shopify_catalog_product_id');

        if ($shopify->variant_id) {
            $row = (clone $base)
                ->where('v.shopify_variant_id', $shopify->variant_id)
                ->orderByDesc('v.synced_at')
                ->orderByDesc('v.id')
                ->select($select)
                ->first();

            if ($row) {
                return $row;
            }
        }

        $sku = trim((string) $shopify->sku);
        if ($sku === '') {
            return null;
        }

        return (clone $base)
            ->whereRaw('LOWER(TRIM(COALESCE(v.sku, \'\'))) = ?', [mb_strtolower($sku)])
            ->orderByDesc('v.synced_at')
            ->orderByDesc('v.id')
            ->select($select)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $ae
     * @return array<int, array{language: ?string, html: string}>
     */
    protected function extractProductDescriptions(array $ae): array
    {
        $candidates = [];
        $list = $this->list($ae['multi_language_description_list'] ?? $ae['aeop_a_e_product_description'] ?? []);

        if ($list !== []) {
            foreach ($list as $desc) {
                $desc = $this->arr($desc);
                $candidates[] = [
                    'language' => $this->str($desc['language'] ?? $desc['locale'] ?? null),
                    'web' => $desc['web_detail'] ?? $desc['detail'] ?? $desc['description'] ?? null,
                    'mobile' => $desc['mobile_detail'] ?? $desc['mobile_desc'] ?? null,
                ];
            }
        } else {
            foreach (['detail', 'product_description'] as $key) {
                if (! empty($ae[$key])) {
                    $candidates[] = ['language' => null, 'web' => $ae[$key], 'mobile' => null];
                }
            }
            if (! empty($ae['mobile_detail'])) {
                $candidates[] = ['language' => null, 'web' => null, 'mobile' => $ae['mobile_detail']];
            }
        }

        $out = [];
        $seenHashes = [];

        foreach ($candidates as $candidate) {
            $webHtml = $this->renderDescriptionContent($candidate['web']);
            $mobileHtml = $this->renderDescriptionContent($candidate['mobile']);
            $html = $this->pickBestDescriptionHtml($webHtml, $mobileHtml);
            if ($html === null) {
                continue;
            }

            $hash = $this->descriptionContentHash($html);
            if (isset($seenHashes[$hash])) {
                continue;
            }
            $seenHashes[$hash] = true;

            $out[] = [
                'language' => $candidate['language'],
                'html' => $html,
            ];
        }

        return $out;
    }

    protected function pickBestDescriptionHtml(?string $web, ?string $mobile): ?string
    {
        if ($web && $mobile) {
            if ($this->descriptionContentHash($web) === $this->descriptionContentHash($mobile)) {
                return $web;
            }

            return strlen(strip_tags($web)) >= strlen(strip_tags($mobile)) ? $web : $mobile;
        }

        return $web ?? $mobile;
    }

    protected function descriptionContentHash(string $html): string
    {
        $text = preg_replace('/\s+/', ' ', trim(strip_tags($html)) ?? '');

        return md5($text);
    }

    /**
     * AliExpress descriptions are often JSON module trees (moduleList / mobileDetail), not plain HTML.
     */
    protected function renderDescriptionContent(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        if (is_array($raw)) {
            $html = $this->renderDescriptionModules($raw);

            return $html !== '' ? $html : null;
        }

        if (! is_string($raw)) {
            return null;
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        if ($trimmed[0] === '{' || $trimmed[0] === '[') {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $html = $this->renderDescriptionModules($decoded);
                if ($html !== '') {
                    return $html;
                }
            }
        }

        if (stripos($trimmed, '<') !== false && stripos($trimmed, '>') !== false) {
            return $trimmed;
        }

        return '<p>'.nl2br(e($trimmed), false).'</p>';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function renderDescriptionModules(array $data): string
    {
        $modules = $this->list(
            $data['moduleList']
            ?? $data['mobileDetail']
            ?? $data['module_list']
            ?? (isset($data[0]['type']) ? $data : [])
        );

        if ($modules === []) {
            return '';
        }

        $html = '';
        foreach ($modules as $module) {
            $module = $this->arr($module);
            $type = strtolower((string) ($module['type'] ?? ''));

            if ($type === 'html') {
                $content = $module['html']['content'] ?? $module['content'] ?? null;
                if (is_string($content) && trim($content) !== '') {
                    $html .= $content;
                }
                continue;
            }

            if ($type === 'text') {
                foreach ($this->list($module['texts'] ?? []) as $text) {
                    $text = $this->arr($text);
                    $content = trim((string) ($text['content'] ?? ''));
                    if ($content === '') {
                        continue;
                    }
                    $class = strtolower((string) ($text['class'] ?? $text['style'] ?? ''));
                    if (str_contains($class, 'title') || str_contains($class, 'head')) {
                        $html .= '<h5 class="ae-desc-title">'.e($content).'</h5>';
                    } else {
                        $html .= '<p class="ae-desc-body">'.nl2br(e($content), false).'</p>';
                    }
                }
                continue;
            }

            if ($type === 'image') {
                foreach ($this->list($module['images'] ?? []) as $image) {
                    $image = $this->arr($image);
                    $url = $this->str($image['url'] ?? $image['imgUrl'] ?? $image['image_url'] ?? null);
                    if ($url === null) {
                        continue;
                    }
                    $width = (int) ($image['width'] ?? $image['style']['width'] ?? 0);
                    $style = $width > 0 ? 'max-width:'.min($width, 800).'px;' : 'max-width:100%;';
                    $html .= '<div class="ae-desc-image my-2"><img src="'.e($url).'" alt="" class="img-fluid rounded border" style="'.$style.'"></div>';
                }
            }
        }

        return trim($html);
    }

    /**
     * @param  array<string, mixed>  $ae
     * @param  array<int, array<string, mixed>>  $aeSkuRows
     * @return array<int, array<string, mixed>>
     */
    protected function formatProductVariants(array $ae, array $aeSkuRows, ?AliexpressMetric $metric = null, ?ShopifySku $shopify = null): array
    {
        $variants = [];

        $skuList = $this->list(
            $ae['aeop_a_e_product_sku_list']
            ?? $ae['aeop_ae_product_sku_list']
            ?? $ae['aeop_a_e_product_s_k_u_list']
            ?? []
        );

        if ($skuList !== []) {
            foreach ($skuList as $skuRow) {
                $skuRow = $this->arr($skuRow);
                $variants[] = [
                    'sku' => $this->str($skuRow['sku_code'] ?? $skuRow['sku'] ?? null),
                    'price' => $this->money($skuRow['sku_price'] ?? $skuRow['price'] ?? null),
                    'stock' => $skuRow['ipm_sku_stock'] ?? $skuRow['sku_stock'] ?? $skuRow['stock'] ?? null,
                    'image' => $this->str($skuRow['sku_image'] ?? $skuRow['image'] ?? null),
                    'ean' => $this->str($skuRow['ean_code'] ?? $skuRow['barcode'] ?? null),
                    'properties' => $this->formatSkuProperties($skuRow),
                ];
            }
        }

        if ($variants === [] && $aeSkuRows !== []) {
            foreach ($aeSkuRows as $row) {
                $variants[] = [
                    'sku' => $this->str($row['sku'] ?? null),
                    'price' => $this->money($row['price'] ?? null),
                    'stock' => $row['stock'] ?? null,
                    'image' => null,
                    'ean' => null,
                    'properties' => [],
                ];
            }
        }

        if ($variants === [] && $metric && $metric->product_id && $metric->sku && $metric->sku !== $metric->product_id) {
            $variants[] = [
                'sku' => $this->str($metric->sku),
                'price' => $this->money($metric->price),
                'stock' => null,
                'image' => null,
                'ean' => null,
                'properties' => [],
                'source' => 'cached',
            ];
        }

        if ($variants === [] && $shopify && $shopify->sku) {
            $variants[] = [
                'sku' => $this->str($shopify->sku),
                'price' => $this->money($shopify->b2c_price ?? $shopify->price),
                'stock' => $shopify->available_to_sell ?? $shopify->inv ?? $shopify->on_hand,
                'image' => $shopify->image_src,
                'ean' => null,
                'properties' => [],
                'source' => 'shopify',
            ];
        }

        return $variants;
    }

    /**
     * @param  array<string, mixed>  $ae
     * @return array<int, array{name: string, value: string}>
     */
    protected function extractProductProperties(array $ae): array
    {
        $out = [];
        foreach (['aeop_a_e_product_propertys', 'aeop_ae_product_propertys', 'product_properties'] as $key) {
            foreach ($this->list($ae[$key] ?? []) as $prop) {
                $prop = $this->arr($prop);
                $name = $this->str($prop['attr_name'] ?? $prop['name'] ?? null);
                $value = $this->str($prop['attr_value'] ?? $prop['value'] ?? $prop['attr_value_id'] ?? null);
                if ($name && $value && strtolower($name) !== 'image') {
                    $out[] = ['name' => $name, 'value' => $value];
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $ae
     * @return array<int, array{language: ?string, subject: ?string}>
     */
    protected function extractMultiLanguageSubjects(array $ae): array
    {
        $out = [];
        foreach ($this->list($ae['multi_language_subject_list'] ?? []) as $row) {
            $row = $this->arr($row);
            $out[] = [
                'language' => $this->str($row['language'] ?? $row['locale'] ?? null),
                'subject' => $this->str($row['subject'] ?? $row['title'] ?? null),
            ];
        }

        return array_values(array_filter($out, fn ($r) => ($r['subject'] ?? '') !== ''));
    }

    /**
     * @param  array<string, mixed>  $order
     * @param  Collection<int, AliexpressOrderMetric>  $lines
     * @return array<string, mixed>
     */
    protected function extractOrderAmounts(array $order, Collection $lines): array
    {
        return [
            'order_total' => $this->money($order['order_amount'] ?? $order['total_amount'] ?? null)
                ?? $this->sumLineTotals($lines),
            'pay_amount' => $this->money($order['pay_amount'] ?? null),
            'shipping_cost' => $this->money($order['logistics_amount'] ?? $order['shipping_cost'] ?? null),
            'discount' => $this->money($order['discount_amount'] ?? $order['promotion_amount'] ?? null),
            'tax' => $this->money($order['tax_amount'] ?? null),
            'currency' => $this->moneyCurrency($order['order_amount'] ?? $order['pay_amount'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<int, array<string, mixed>>
     */
    protected function extractLogisticsList(array $order): array
    {
        $out = [];
        $list = $this->list(
            $order['logistics_info_list']['aeop_tp_logistics_info_dto']
            ?? $order['logistics_info_list']
            ?? $order['child_order_list']
            ?? []
        );

        foreach ($list as $row) {
            $row = $this->arr($row);
            $out[] = [
                'service' => $this->str($row['logistics_service_name'] ?? $row['logistics_type'] ?? null),
                'tracking' => $this->str($row['logistics_no'] ?? $row['tracking_number'] ?? null),
                'status' => $this->str($row['logistics_status'] ?? null),
                'send_type' => $this->str($row['send_type'] ?? null),
                'receive_status' => $this->str($row['receive_status'] ?? null),
            ];
        }

        if ($out === [] && ! empty($order['logistics_no'])) {
            $out[] = [
                'service' => $this->str($order['logistics_type'] ?? null),
                'tracking' => $this->str($order['logistics_no']),
                'status' => null,
                'send_type' => null,
                'receive_status' => null,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $order
     * @param  Collection<int, AliexpressOrderMetric>  $lines
     * @return array<int, array<string, mixed>>
     */
    protected function extractRichOrderLines(array $order, Collection $lines): array
    {
        $apiProducts = $this->list(
            $order['product_list']['order_product_dto']
            ?? $order['product_list']['aeop_order_product_dto']
            ?? $order['product_list']
            ?? []
        );

        $bySku = [];
        foreach ($lines as $line) {
            $bySku[(string) $line->sku] = $line;
        }

        $out = [];
        foreach ($apiProducts as $product) {
            $product = $this->arr($product);
            $sku = $this->str($product['sku_code'] ?? $product['sku'] ?? null) ?: '__unknown__';
            $db = $bySku[$sku] ?? null;
            $unit = $product['product_unit_price'] ?? $product['total_product_amount'] ?? null;

            $out[] = [
                'sku' => $sku,
                'product_id' => $this->str($product['product_id'] ?? $db?->product_id),
                'title' => $this->str($product['product_name'] ?? $product['subject'] ?? $db?->display_title),
                'quantity' => (int) ($product['product_count'] ?? $product['quantity'] ?? $db?->quantity ?? 1),
                'unit_price' => $this->money($unit),
                'line_total' => $this->multiplyMoney($this->money($unit), (int) ($product['product_count'] ?? 1)),
                'image' => $this->str($product['product_img_url'] ?? $product['snapshot_small_photo_path'] ?? $product['product_image'] ?? null),
                'child_order_id' => $this->str($product['child_order_id'] ?? $product['order_sort_id'] ?? null),
                'status' => $this->str($product['order_status'] ?? $product['logistics_status'] ?? null),
                'import_status' => $db?->import_status,
                'shopify_order_id' => $db?->shopify_order_id,
            ];
        }

        if ($out === []) {
            foreach ($lines as $line) {
                $rawLine = is_array($line->raw_payload) ? ($line->raw_payload['line'] ?? []) : [];
                $out[] = [
                    'sku' => $line->sku,
                    'product_id' => $line->product_id,
                    'title' => $line->display_title,
                    'quantity' => $line->quantity ?? 1,
                    'unit_price' => is_numeric($line->amount) ? (float) $line->amount : null,
                    'line_total' => is_numeric($line->amount) ? (float) $line->amount * max(1, (int) $line->quantity) : null,
                    'image' => $this->str($rawLine['product_img_url'] ?? $rawLine['snapshot_small_photo_path'] ?? null),
                    'child_order_id' => null,
                    'status' => $line->status,
                    'import_status' => $line->import_status,
                    'shopify_order_id' => $line->shopify_order_id,
                ];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $skuRow
     * @return array<int, array{name: string, value: string}>
     */
    protected function formatSkuProperties(array $skuRow): array
    {
        $out = [];
        foreach ($this->list($skuRow['aeop_s_k_u_property_list'] ?? $skuRow['sku_property_list'] ?? []) as $prop) {
            $prop = $this->arr($prop);
            $name = $this->str($prop['sku_property_name'] ?? $prop['property_name'] ?? null);
            $value = $this->str($prop['property_value_definition_name'] ?? $prop['sku_property_value'] ?? null);
            if ($name && $value) {
                $out[] = ['name' => $name, 'value' => $value];
            }
        }

        return $out;
    }

    protected function extractProductTitle(array $ae): ?string
    {
        foreach (['subject', 'product_name', 'title', 'product_title'] as $key) {
            if (! empty($ae[$key]) && is_string($ae[$key])) {
                return trim($ae[$key]);
            }
        }

        $subjects = $this->extractMultiLanguageSubjects($ae);

        return $subjects[0]['subject'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $addr
     */
    protected function joinAddress(array $addr): ?string
    {
        $parts = array_filter([
            $addr['contact_person'] ?? $addr['receiver'] ?? null,
            $addr['address'] ?? $addr['detail_address'] ?? null,
            $addr['address2'] ?? null,
            $addr['city'] ?? null,
            $addr['province'] ?? $addr['state'] ?? null,
            $addr['zip'] ?? $addr['zip_code'] ?? null,
            $addr['country'] ?? null,
        ]);

        return $parts !== [] ? implode(', ', $parts) : null;
    }

    /**
     * @param  Collection<int, AliexpressOrderMetric>  $lines
     */
    protected function sumLineTotals(Collection $lines): ?float
    {
        $sum = $lines->sum(fn ($row) => is_numeric($row->amount) ? (float) $row->amount * max(1, (int) $row->quantity) : 0);

        return $sum > 0 ? $sum : null;
    }

    /**
     * @return array<int, string>
     */
    protected function splitImageUrls(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(fn ($v) => $this->str($v), $value)));
        }
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $parts = preg_split('/[;,\s]+/', trim($value)) ?: [];

        return array_values(array_filter(array_map(fn ($v) => $this->str($v), $parts)));
    }

    protected function money(mixed $value): ?float
    {
        if (is_array($value)) {
            $value = $value['amount'] ?? $value['value'] ?? null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    protected function moneyCurrency(mixed $value): ?string
    {
        if (is_array($value)) {
            return $this->str($value['currency_code'] ?? $value['currency'] ?? null);
        }

        return null;
    }

    protected function multiplyMoney(?float $amount, int $qty): ?float
    {
        if ($amount === null) {
            return null;
        }

        return $amount * max(1, $qty);
    }

    protected function str(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_scalar($value)) {
            $s = trim((string) $value);

            return $s !== '' ? $s : null;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function arr(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            return json_decode(json_encode($value), true) ?: [];
        }

        return [];
    }

    /**
     * @return array<int, mixed>
     */
    protected function list(mixed $list): array
    {
        $list = $this->arr($list);
        if ($list === []) {
            return [];
        }
        if (! isset($list[0]) && (isset($list['product_id']) || isset($list['sku_code']) || isset($list['order_id']) || isset($list['attr_name']))) {
            return [$list];
        }

        return array_values($list);
    }
}
