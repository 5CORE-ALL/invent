<?php

namespace App\Services\MarketplaceManager;

use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\WayfairDataView;
use App\Models\WayfairListingStatus;
use App\Models\WayfairPricingPrice;
use App\Services\WayfairApiService;
use App\Support\Marketplace\ChannelListingRegistry;
use App\Support\Marketplace\ListingChannelCounts;
use App\Support\Marketplace\ListingCountsEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Publish Missing L SKUs to Wayfair via productAddition.submit.
 */
class WayfairListingPublishService
{
    public function __construct(private WayfairApiService $api)
    {
    }

    /**
     * @return array{id: int, path: string, name: string}
     */
    public function suggestClassForSku(string $sku): array
    {
        $empty = ['id' => 0, 'path' => '', 'name' => ''];
        $sku = trim($sku);
        if ($sku === '') {
            return $empty;
        }

        $product = $this->findProduct($sku);
        $candidates = [$sku];
        if ($product) {
            $parent = trim((string) ($product->parent ?? ''));
            if ($parent !== '') {
                $siblings = ProductMaster::query()
                    ->whereNull('deleted_at')
                    ->where('parent', $parent)
                    ->whereRaw('UPPER(TRIM(sku)) NOT LIKE ?', ['PARENT%'])
                    ->orderBy('sku')
                    ->limit(80)
                    ->pluck('sku')
                    ->all();
                foreach ($siblings as $sibling) {
                    $candidates[] = (string) $sibling;
                }
            }
        }

        $cached = $this->classIdFromListingStatuses($candidates);
        if ($cached > 0) {
            return ['id' => $cached, 'path' => 'Class '.$cached.' (from a listed sibling)', 'name' => ''];
        }

        $hit = $this->api->lookupCatalogClassForSkus($candidates);
        if ($hit && ($hit['class_id'] ?? 0) > 0) {
            $name = trim((string) ($hit['class_name'] ?? ''));
            $this->rememberClassId($candidates, (int) $hit['class_id'], $name);

            return [
                'id' => (int) $hit['class_id'],
                'path' => $name !== '' ? $name.' ('.$hit['class_id'].')' : 'Class '.$hit['class_id'],
                'name' => $name,
            ];
        }

        $fromSubmission = $this->classIdFromProductAdditionSubmissions($candidates);
        if ($fromSubmission > 0) {
            $this->rememberClassId($candidates, $fromSubmission);

            return ['id' => $fromSubmission, 'path' => 'Class '.$fromSubmission.' (from a prior Wayfair submission)', 'name' => ''];
        }

        $default = (int) config('services.wayfair.default_class_id', 0);
        if ($default > 0) {
            return ['id' => $default, 'path' => 'Configured Wayfair class '.$default, 'name' => ''];
        }

        return $empty;
    }

    /**
     * @param  list<string>  $skus
     * @return array{success: bool, message: string, goods_id?: string, sku_id?: string, skus?: list<string>}
     */
    public function publishSkus(array $skus, bool $expandSiblings = true, string $mode = 'variation', string $parentHint = '', ?int $classId = null, ?string $className = null): array
    {
        $skus = $this->uniqueSkus($skus);
        if ($skus === []) {
            return ['success' => false, 'message' => 'SKU is required.'];
        }
        if (! $this->api->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Wayfair API credentials missing. Set WAYFAIR_CLIENT_ID and WAYFAIR_CLIENT_SECRET.',
            ];
        }

        $mode = strtolower(trim($mode)) === 'single' ? 'single' : 'variation';
        if ($expandSiblings && $mode === 'variation') {
            $publishSkus = $this->expandToPublishableSiblings($skus);
        } else {
            $publishSkus = $this->filterPublishable($skus);
        }
        if ($publishSkus === []) {
            return [
                'success' => false,
                'message' => 'No Missing L child SKUs left to publish (already listed, NRL, or missing images).',
            ];
        }

        if ($mode === 'single' && count($publishSkus) > 1) {
            $ok = [];
            $fail = [];
            $listed = [];
            $lastId = null;
            foreach ($publishSkus as $sku) {
                $one = $this->publishSkus([$sku], false, 'single', $parentHint, $classId, $className);
                if ($one['success'] ?? false) {
                    $ok[] = $one['message'] ?? ('Published '.$sku);
                    foreach ($one['skus'] ?? [$sku] as $listedSku) {
                        $listed[] = $listedSku;
                    }
                    if (! empty($one['goods_id'])) {
                        $lastId = $one['goods_id'];
                    }
                } else {
                    $fail[] = $sku.': '.($one['message'] ?? 'Publish failed');
                }
            }

            return [
                'success' => $fail === [],
                'message' => trim(implode(' ', $ok).($fail !== [] ? ' '.implode(' ', $fail) : '')),
                'goods_id' => $lastId,
                'sku_id' => $lastId,
                'skus' => array_values(array_unique($listed)),
            ];
        }

        $resolvedClass = $this->resolveClassId($publishSkus, $classId, $className);
        if ($resolvedClass <= 0) {
            return [
                'success' => false,
                'message' => 'Wayfair class ID is required. Type the class ID in the publish window (from a listed sibling or Partner Home).',
            ];
        }

        $questionRes = $this->api->getProductAdditionQuestions($resolvedClass);
        $questions = $questionRes['questions'] ?? [];
        if ($questions === []) {
            return [
                'success' => false,
                'message' => ($questionRes['message'] ?? '') !== ''
                    ? $questionRes['message']
                    : 'Wayfair returned no product-addition questions for class '.$resolvedClass.'. Check the class ID and WRITE-PRODUCT-ADDITION-SUBMIT access.',
            ];
        }

        $manufacturer = $this->api->getManufacturerAssociation();
        if ($manufacturer === null) {
            return [
                'success' => false,
                'message' => 'Wayfair manufacturer ID is missing. Set WAYFAIR_MANUFACTURER_ID or confirm brandAssociations for this supplier.',
            ];
        }

        $products = $this->findProductsBySkus($publishSkus);
        $prepared = [];
        foreach ($publishSkus as $sku) {
            $product = $products->get($this->normalizeSkuKey($sku));
            if (! $product) {
                return ['success' => false, 'message' => 'SKU not found in product master: '.$sku];
            }
            $title = $this->resolveTitle($product, $sku);
            if ($title === '') {
                return ['success' => false, 'message' => $sku.': Title missing in Title Master'];
            }
            $images = $this->productImages($product, $sku);
            if ($images === []) {
                return ['success' => false, 'message' => 'No public https image for '.$sku.'. Add images on CP / Image Master.'];
            }
            $pkg = $this->packageSize($product, $sku);
            $prepared[] = [
                'sku' => $sku,
                'product' => $product,
                'title' => $title,
                'images' => $images,
                'price' => $this->resolvePrice($sku, $product),
                'inv' => $this->shopifyInv($sku),
                'pkg' => $pkg,
            ];
        }

        $parts = [];
        foreach ($prepared as $row) {
            $parts[] = $this->buildPart($row, $questions, $manufacturer);
        }

        $request = [
            'supplierId' => (int) config('services.wayfair.supplier_id'),
            'rejectAllOnErrors' => true,
            'ignoreWarnings' => true,
            'proposedProductAdditions' => [[
                'classId' => $resolvedClass,
                'marketContext' => $this->api->marketContext(),
                'parts' => $parts,
            ]],
        ];

        $res = $this->api->submitProductAddition($request);
        if (empty($res['success'])) {
            return [
                'success' => false,
                'message' => $res['message'] ?? 'Wayfair product addition failed.',
            ];
        }

        $requestIds = $res['request_ids'] ?? [];
        $pollMessage = $this->pollSubmissionFlaws($requestIds);
        if ($pollMessage !== '') {
            return [
                'success' => false,
                'message' => $pollMessage,
                'goods_id' => $requestIds[0] ?? null,
            ];
        }

        $requestId = (string) ($requestIds[0] ?? '');
        try {
            $this->persistListed($prepared, $requestId, $resolvedClass);
            $this->pushInventoryAndPrice($prepared);
        } catch (\Throwable $e) {
            Log::warning('Wayfair persist listed failed', ['error' => $e->getMessage()]);
        }
        $this->forgetListingCaches();

        return [
            'success' => true,
            'message' => 'Submitted '.count($prepared).' SKU(s) to Wayfair product addition'
                .($requestId !== '' ? ' (request '.$requestId.').' : '.'),
            'goods_id' => $requestId,
            'sku_id' => $requestId,
            'skus' => $publishSkus,
        ];
    }

    /**
     * @param  list<string>  $skus
     */
    private function resolveClassId(array $skus, ?int $classId, ?string $className): int
    {
        if ($classId !== null && $classId > 0) {
            return $classId;
        }
        $name = trim((string) $className);
        if ($name !== '' && preg_match('/(\d{2,})/', $name, $match)) {
            return (int) $match[1];
        }
        $fromStatus = $this->classIdFromListingStatuses($skus);
        if ($fromStatus > 0) {
            return $fromStatus;
        }
        $suggested = $this->suggestClassForSku($skus[0] ?? '');

        return (int) ($suggested['id'] ?? 0);
    }

    /**
     * @param  list<string>  $skus
     */
    private function classIdFromListingStatuses(array $skus): int
    {
        if ($skus === [] || ! Schema::hasTable('wayfair_listing_statuses')) {
            return 0;
        }
        $want = [];
        foreach ($skus as $sku) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
            if ($norm !== '') {
                $want[$norm] = true;
            }
        }
        $rows = WayfairListingStatus::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderByDesc('id')
            ->limit(4000)
            ->get(['sku', 'value']);
        foreach ($rows as $row) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku);
            if ($want !== [] && ! isset($want[$norm])) {
                continue;
            }
            $id = $this->classIdFromStatusValue(is_array($row->value) ? $row->value : []);
            if ($id > 0) {
                return $id;
            }
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function classIdFromStatusValue(array $value): int
    {
        foreach (['class_id', 'wayfair_class_id', 'classId', 'taxonomy_category_id', 'taxonomyCategoryId'] as $key) {
            $id = (int) ($value[$key] ?? 0);
            if ($id > 0) {
                return $id;
            }
        }
        $class = $value['class'] ?? null;
        if (is_array($class)) {
            $id = (int) ($class['class_id'] ?? $class['classId'] ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return 0;
    }

    /**
     * @param  list<string>  $skus
     */
    private function classIdFromProductAdditionSubmissions(array $skus): int
    {
        if ($skus === [] || ! Schema::hasTable('wayfair_listing_statuses')) {
            return 0;
        }
        $ids = [];
        $rows = WayfairListingStatus::query()->whereIn('sku', $skus)->get(['sku', 'value']);
        foreach ($rows as $row) {
            $value = is_array($row->value) ? $row->value : [];
            foreach (['wayfair_request_id', 'listing_id', 'request_id'] as $key) {
                $id = trim((string) ($value[$key] ?? ''));
                if ($id !== '' && ! in_array($id, $ids, true)) {
                    $ids[] = $id;
                }
            }
        }
        if ($ids === []) {
            return 0;
        }
        try {
            foreach ($this->api->getProductAdditionSubmissions(array_slice($ids, 0, 10)) as $row) {
                $classId = (int) ($row['classId'] ?? $row['class_id'] ?? 0);
                if ($classId > 0) {
                    return $classId;
                }
            }
        } catch (\Throwable) {
        }

        return 0;
    }

    /**
     * @param  list<string>  $skus
     */
    private function rememberClassId(array $skus, int $classId, string $className = ''): void
    {
        if ($classId <= 0 || ! Schema::hasTable('wayfair_listing_statuses')) {
            return;
        }
        foreach (array_slice($skus, 0, 8) as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '') {
                continue;
            }
            $row = WayfairListingStatus::query()
                ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
                ->orderByDesc('id')
                ->first();
            $value = ($row && is_array($row->value)) ? $row->value : [];
            if ((int) ($value['class_id'] ?? 0) === $classId) {
                continue;
            }
            $value['class_id'] = $classId;
            if ($className !== '') {
                $value['class_name'] = $className;
            }
            try {
                WayfairListingStatus::upsertBySku($sku, $value);
            } catch (\Throwable) {
            }
        }
    }

    /**
     * @param  array{sku: string, product: ProductMaster, title: string, images: list<string>, price: ?float, inv: int, pkg: array<string, mixed>}  $row
     * @param  list<array<string, mixed>>  $questions
     * @param  array{id: int, name: string}  $manufacturer
     * @return array<string, mixed>
     */
    private function buildPart(array $row, array $questions, array $manufacturer): array
    {
        $sku = $row['sku'];
        $product = $row['product'];
        $collection = trim((string) ($product->parent ?? '')) ?: $sku;
        $bullets = $this->featureBullets($product, $row['title']);
        $part = [
            'productName' => $row['title'],
            'collectionName' => $collection,
            'supplierPartNumber' => $sku,
            'manufacturerId' => (int) $manufacturer['id'],
            'manufacturerPartNumber' => $sku,
            'marketingCopy' => $this->marketingCopy($product, $row['title']),
            'featureBullets' => $bullets,
            'media' => [
                'images' => array_slice($row['images'], 0, 8),
            ],
        ];
        $upc = $this->resolveUpc($product);
        if ($upc !== '') {
            $part['universalProductCode'] = $upc;
        }
        $url = $this->manufacturerUrl($sku);
        if ($url !== '') {
            $part['manufacturerProductUrl'] = $url;
        }

        $ctx = [
            'sku' => $sku,
            'title' => $row['title'],
            'collection' => $collection,
            'product' => $product,
            'pkg' => $row['pkg'],
            'manufacturer' => $manufacturer,
            'upc' => $upc,
            'bullets' => $bullets,
        ];
        $part['answers'] = $this->buildAnswers($questions, $ctx);

        return $part;
    }

    /**
     * @param  list<array<string, mixed>>  $questions
     * @param  array<string, mixed>  $ctx
     * @return list<array<string, mixed>>
     */
    private function buildAnswers(array $questions, array $ctx): array
    {
        $answers = [];
        foreach ($questions as $question) {
            if (! is_array($question)) {
                continue;
            }
            $children = is_array($question['childQuestions'] ?? null) ? $question['childQuestions'] : [];
            if ($children !== []) {
                $answers = array_merge($answers, $this->buildAnswers($children, $ctx));
                continue;
            }
            $id = trim((string) ($question['id'] ?? ''));
            if ($id === '' || $this->isCoreAutoQuestion($id)) {
                continue;
            }
            if (($question['isActive'] ?? true) === false) {
                continue;
            }
            $mapped = $this->mapQuestionAnswer($question, $ctx);
            if ($mapped !== []) {
                $answers = array_merge($answers, $mapped);
                continue;
            }
            $importance = strtoupper((string) ($question['importanceType'] ?? ''));
            if (in_array($importance, ['REQUIRED', 'RECOMMENDED'], true)) {
                $answers = array_merge($answers, $this->fallbackAnswer($question));
            }
        }

        return $answers;
    }

    private function isCoreAutoQuestion(string $id): bool
    {
        $id = strtolower($id);

        return in_array($id, [
            'core::amazonstandardidentificationnumber',
            'core::collectionname',
            'core::manufacturerpartnumber',
            'core::manufacturerproducturl',
            'core::productname',
            'core::universalproductcode',
            'featuredescription::genericfeatures',
        ], true);
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  array<string, mixed>  $ctx
     * @return list<array<string, mixed>>
     */
    private function mapQuestionAnswer(array $question, array $ctx): array
    {
        $id = (string) ($question['id'] ?? '');
        $hay = strtolower($id.' '.((string) ($question['displayName'] ?? '')).' '.((string) ($question['internalName'] ?? '')));
        $pkg = is_array($ctx['pkg'] ?? null) ? $ctx['pkg'] : [];
        $product = $ctx['product'];

        if (preg_match('/\b(weight|lb|pound|pounds)\b/', $hay) && ! str_contains($hay, 'capacity')) {
            $lb = $pkg['weight_lb'] ?? null;
            if (is_numeric($lb) && (float) $lb > 0) {
                return [$this->answer($id, number_format((float) $lb, 2, '.', ''))];
            }
        }
        if (preg_match('/\b(length|depth)\b/', $hay)) {
            $n = $pkg['length_in'] ?? null;
            if (is_numeric($n) && (float) $n > 0) {
                return [$this->answer($id, number_format((float) $n, 2, '.', ''))];
            }
        }
        if (preg_match('/\bwidth\b/', $hay)) {
            $n = $pkg['width_in'] ?? null;
            if (is_numeric($n) && (float) $n > 0) {
                return [$this->answer($id, number_format((float) $n, 2, '.', ''))];
            }
        }
        if (preg_match('/\bheight\b/', $hay)) {
            $n = $pkg['height_in'] ?? null;
            if (is_numeric($n) && (float) $n > 0) {
                return [$this->answer($id, number_format((float) $n, 2, '.', ''))];
            }
        }
        if (preg_match('/country of origin|made in|origin country/', $hay)) {
            return $this->choiceOrValue($question, ['China', 'CHN', 'CN', 'People\'s Republic of China']);
        }
        if (preg_match('/\bbrand\b/', $hay)) {
            return $this->choiceOrValue($question, ['5 Core Inc.', '5 Core', $ctx['manufacturer']['name'] ?? '']);
        }
        if (preg_match('/\bcolor\b/', $hay)) {
            return $this->choiceOrValue($question, $this->colorGuess((string) $ctx['sku'], $product));
        }
        if (preg_match('/upc|gtin|barcode/', $hay) && trim((string) ($ctx['upc'] ?? '')) !== '') {
            return [$this->answer($id, (string) $ctx['upc'])];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  list<string>  $wanted
     * @return list<array<string, mixed>>
     */
    private function choiceOrValue(array $question, array $wanted): array
    {
        $id = (string) ($question['id'] ?? '');
        $options = $this->possibleValues($question);
        foreach ($wanted as $want) {
            $want = trim((string) $want);
            if ($want === '') {
                continue;
            }
            foreach ($options as $option) {
                if (strcasecmp($option, $want) === 0 || stripos($option, $want) !== false || stripos($want, $option) !== false) {
                    return [$this->answer($id, $option)];
                }
            }
            if ($options === []) {
                return [$this->answer($id, $want)];
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $question
     * @return list<array<string, mixed>>
     */
    private function fallbackAnswer(array $question): array
    {
        $id = (string) ($question['id'] ?? '');
        $type = strtoupper((string) ($question['answerType'] ?? ''));
        $options = $this->possibleValues($question);

        if (! empty($question['isNotApplicableEligible'])) {
            foreach (['Does Not Apply', 'Does not apply', 'N/A'] as $na) {
                if ($options === [] || in_array($na, $options, true)) {
                    return [$this->answer($id, $na)];
                }
            }
        }
        if (! empty($question['isUnavailableEligible'])) {
            foreach (['Unavailable', 'unavailable'] as $na) {
                if ($options === [] || in_array($na, $options, true)) {
                    return [$this->answer($id, $na)];
                }
            }
        }
        if ($type === 'BOOLEAN') {
            foreach ($options as $option) {
                if (strcasecmp($option, 'No') === 0) {
                    return [$this->answer($id, $option)];
                }
            }

            return [$this->answer($id, 'No')];
        }
        foreach ($options as $option) {
            if (! preg_match('/unavailable|does not apply|n\/a/i', $option)) {
                return [$this->answer($id, $option)];
            }
        }
        if ($options !== []) {
            return [$this->answer($id, $options[0])];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $question
     * @return list<string>
     */
    private function possibleValues(array $question): array
    {
        $out = [];
        foreach ($question['possibleAnswers'] ?? [] as $row) {
            $value = is_array($row) ? trim((string) ($row['value'] ?? $row['key'] ?? '')) : trim((string) $row);
            if ($value !== '' && ! in_array($value, $out, true)) {
                $out[] = $value;
            }
        }

        return $out;
    }

    /**
     * @return array{questionId: string, value: string}
     */
    private function answer(string $id, string $value): array
    {
        return ['questionId' => $id, 'value' => $value];
    }

    /**
     * @param  list<string>  $ids
     */
    private function pollSubmissionFlaws(array $ids): string
    {
        if ($ids === []) {
            return '';
        }
        $last = [];
        for ($i = 0; $i < 6; $i++) {
            if ($i > 0) {
                usleep(1500000);
            }
            $last = $this->api->getProductAdditionSubmissions($ids);
            if ($last === []) {
                continue;
            }
            $errors = [];
            $pending = false;
            foreach ($last as $row) {
                $status = strtoupper((string) ($row['validationStatus'] ?? $row['status'] ?? ''));
                if (in_array($status, ['PENDING', 'IN_PROGRESS', 'PROCESSING', 'SUBMITTED'], true)) {
                    $pending = true;
                }
                foreach ($row['validationFlaws'] ?? [] as $flaw) {
                    if (! is_array($flaw)) {
                        continue;
                    }
                    if (strtoupper((string) ($flaw['flawType'] ?? '')) !== 'ERROR') {
                        continue;
                    }
                    $text = trim((string) ($flaw['flaw'] ?? ''));
                    if ($text !== '') {
                        $errors[] = $text;
                    }
                }
            }
            if ($errors !== []) {
                return implode(' ', array_slice(array_unique($errors), 0, 6));
            }
            if (! $pending) {
                return '';
            }
        }

        return '';
    }

    /**
     * @param  list<array{sku: string, price: ?float, inv: int}>  $prepared
     */
    private function persistListed(array $prepared, string $requestId, int $classId = 0): void
    {
        foreach ($prepared as $row) {
            $sku = trim((string) $row['sku']);
            if ($sku === '') {
                continue;
            }
            $existing = [];
            $status = WayfairListingStatus::query()
                ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
                ->orderByDesc('id')
                ->first();
            if ($status && is_array($status->value)) {
                $existing = $status->value;
            }
            $existing['listed'] = 'Listed';
            if ($requestId !== '') {
                $existing['listing_id'] = $requestId;
                $existing['wayfair_request_id'] = $requestId;
            }
            if ($classId > 0) {
                $existing['class_id'] = $classId;
            }
            WayfairListingStatus::upsertBySku($sku, $existing);

            $attrs = ['wayfair_stock' => max(0, (int) ($row['inv'] ?? 0))];
            if (($row['price'] ?? null) !== null && (float) $row['price'] > 0) {
                $attrs['price'] = (float) $row['price'];
            }
            if (Schema::hasTable('wayfair_pricing_prices')) {
                WayfairPricingPrice::upsertBySku($sku, $attrs);
            }
        }
    }

    /**
     * @param  list<array{sku: string, price: ?float, inv: int}>  $prepared
     */
    private function pushInventoryAndPrice(array $prepared): void
    {
        $items = [];
        foreach ($prepared as $row) {
            $items[] = [
                'sku' => $row['sku'],
                'quantity' => max(0, (int) ($row['inv'] ?? 0)),
            ];
            if (($row['price'] ?? null) !== null && (float) $row['price'] > 0) {
                try {
                    $this->api->updatePrice((string) $row['sku'], (float) $row['price']);
                } catch (\Throwable $e) {
                    Log::info('Wayfair publish price push skipped', [
                        'sku' => $row['sku'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
        if ($items !== []) {
            try {
                $this->api->updateItemInventoryBulk($items);
            } catch (\Throwable $e) {
                Log::info('Wayfair publish inventory push skipped', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * @param  list<string>  $seedSkus
     * @return list<string>
     */
    private function expandToPublishableSiblings(array $seedSkus): array
    {
        $seeds = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereIn('sku', $seedSkus)
            ->get();
        $parentKeys = [];
        foreach ($seeds as $product) {
            $parentKeys[$this->groupKey($product)] = true;
        }
        $children = collect();
        foreach (array_keys($parentKeys) as $parent) {
            $group = ProductMaster::query()
                ->whereNull('deleted_at')
                ->where('parent', $parent)
                ->whereRaw('UPPER(TRIM(sku)) NOT LIKE ?', ['PARENT%'])
                ->orderBy('sku')
                ->get();
            if ($group->isEmpty()) {
                $group = $seeds->filter(function ($product) use ($parent) {
                    return $this->groupKey($product) === $parent
                        && stripos((string) $product->sku, 'PARENT') === false;
                })->values();
            }
            $children = $children->concat($group);
        }

        return $this->filterPublishable(
            $children->map(fn ($p) => trim((string) $p->sku))->filter()->unique()->values()->all()
        );
    }

    /**
     * @param  list<string>  $skus
     * @return list<string>
     */
    private function filterPublishable(array $skus): array
    {
        $cfg = ChannelListingRegistry::get('wayfair');
        $listedMap = $cfg ? ChannelListingRegistry::loadListedIds($cfg, $skus) : [];
        $nrValues = class_exists(WayfairDataView::class)
            ? ListingCountsEngine::loadNrValues(WayfairDataView::class, $skus)
            : collect();
        $products = $this->findProductsBySkus($skus);
        $out = [];
        foreach ($skus as $sku) {
            $sku = trim($sku);
            if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                continue;
            }
            $norm = strtolower($sku);
            if (trim((string) ($listedMap[$norm] ?? $listedMap[$this->normalizeSkuKey($sku)] ?? '')) !== '') {
                continue;
            }
            if (ListingCountsEngine::nrReqFromDataView($nrValues->get(strtoupper($sku))) === 'NR') {
                continue;
            }
            $product = $products->get($this->normalizeSkuKey($sku));
            if (! $product || $this->productImages($product, $sku) === []) {
                continue;
            }
            $out[] = $sku;
        }

        return $out;
    }

    /**
     * @param  list<string>  $skus
     * @return \Illuminate\Support\Collection<string, ProductMaster>
     */
    private function findProductsBySkus(array $skus): \Illuminate\Support\Collection
    {
        $wanted = [];
        foreach ($skus as $sku) {
            $key = $this->normalizeSkuKey((string) $sku);
            if ($key !== '') {
                $wanted[$key] = (string) $sku;
            }
        }
        if ($wanted === []) {
            return collect();
        }

        return ProductMaster::query()
            ->whereNull('deleted_at')
            ->where(function ($q) use ($wanted) {
                $q->whereIn('sku', array_values($wanted));
                foreach (array_keys($wanted) as $key) {
                    $q->orWhereRaw('UPPER(TRIM(sku)) = ?', [$key]);
                }
            })
            ->get()
            ->keyBy(fn ($row) => $this->normalizeSkuKey((string) $row->sku));
    }

    private function findProduct(string $sku): ?ProductMaster
    {
        return ProductMaster::query()
            ->whereNull('deleted_at')
            ->where('sku', $sku)
            ->first()
            ?: ProductMaster::query()
                ->whereNull('deleted_at')
                ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
                ->first();
    }

    private function resolveTitle(ProductMaster $product, string $sku): string
    {
        foreach (['title80', 'title100', 'title150', 'title60'] as $field) {
            $title = trim((string) ($product->{$field} ?? ''));
            if ($title !== '') {
                return $title;
            }
        }
        $shopify = ShopifySku::mapByProductSkus([$sku])->get($sku);

        return trim((string) ($shopify->product_title ?? $shopify->title ?? $product->parent ?? $sku));
    }

    private function resolvePrice(string $sku, ProductMaster $product): ?float
    {
        if (Schema::hasTable('wayfair_pricing_prices')) {
            $row = WayfairPricingPrice::query()
                ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
                ->first();
            if ($row && is_numeric($row->price) && (float) $row->price > 0) {
                return round((float) $row->price, 2);
            }
        }
        $shopify = ShopifySku::mapByProductSkus([$sku])->get($sku);
        $price = (float) ($shopify->price ?? $shopify->b2c_price ?? 0);
        if ($price > 0) {
            return round($price, 2);
        }
        $values = is_array($product->Values) ? $product->Values : [];
        foreach (['lp', 'LP', 'sprice', 'SPRICE', 'price'] as $key) {
            if (isset($values[$key]) && is_numeric($values[$key]) && (float) $values[$key] > 0) {
                return round((float) $values[$key], 2);
            }
        }

        return null;
    }

    private function shopifyInv(string $sku): int
    {
        $shopify = ShopifySku::mapByProductSkus([$sku])->get($sku);

        return (int) ($shopify->available_to_sell ?? $shopify->inv ?? 0);
    }

    /**
     * @return list<string>
     */
    private function productImages(ProductMaster $product, string $sku = ''): array
    {
        $urls = [];
        $push = function (string $raw) use (&$urls): void {
            $url = $this->absoluteImageUrl($raw);
            if ($url === '' || in_array($url, $urls, true)) {
                return;
            }
            $urls[] = $url;
        };
        for ($i = 1; $i <= 20; $i++) {
            $push((string) ($product->{'image'.$i} ?? ''));
        }
        $push((string) ($product->main_image ?? ''));
        $push((string) ($product->main_image_brand ?? ''));
        $parentSku = trim((string) ($product->parent ?? ''));
        if ($parentSku !== '' && strcasecmp($parentSku, $sku !== '' ? $sku : (string) $product->sku) !== 0) {
            $parent = $this->findProduct($parentSku);
            if ($parent) {
                for ($i = 1; $i <= 20; $i++) {
                    $push((string) ($parent->{'image'.$i} ?? ''));
                }
                $push((string) ($parent->main_image ?? ''));
            }
        }

        return array_slice($urls, 0, 8);
    }

    private function absoluteImageUrl(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        foreach (preg_split('/[|,;]+/', $raw) ?: [] as $one) {
            $one = trim((string) $one);
            if ($one === '') {
                continue;
            }
            if (preg_match('#^https?://#i', $one)) {
                return $one;
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function featureBullets(ProductMaster $product, string $title): array
    {
        $out = [];
        foreach (['bullet1', 'bullet2', 'bullet3', 'bullet4', 'bullet5', 'feature1', 'feature2', 'feature3', 'feature4'] as $field) {
            $value = trim((string) ($product->{$field} ?? ''));
            if ($value !== '' && ! in_array($value, $out, true)) {
                $out[] = $value;
            }
        }
        if ($out === []) {
            $out[] = $title;
        }

        return array_slice($out, 0, 8);
    }

    private function marketingCopy(ProductMaster $product, string $title): string
    {
        foreach (['product_description', 'description_800', 'description_600', 'description_v2_description'] as $field) {
            $value = trim(strip_tags((string) ($product->{$field} ?? '')));
            if ($value !== '') {
                return mb_substr($value, 0, 4000);
            }
        }

        return $title;
    }

    private function resolveUpc(ProductMaster $product): string
    {
        foreach (['upc', 'barcode'] as $field) {
            $value = preg_replace('/\D+/', '', (string) ($product->{$field} ?? '')) ?? '';
            if (strlen($value) >= 8) {
                return $value;
            }
        }
        $values = is_array($product->Values) ? $product->Values : [];
        foreach (['upc', 'gtin', 'ean', 'barcode'] as $key) {
            $value = preg_replace('/\D+/', '', (string) ($values[$key] ?? '')) ?? '';
            if (strlen($value) >= 8) {
                return $value;
            }
        }

        return '';
    }

    private function manufacturerUrl(string $sku): string
    {
        $shopify = ShopifySku::mapByProductSkus([$sku])->get($sku);
        $handle = trim((string) ($shopify->handle ?? ''));
        if ($handle !== '') {
            return 'https://5core.com/products/'.$handle;
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function colorGuess(string $sku, ProductMaster $product): array
    {
        $values = is_array($product->Values) ? $product->Values : [];
        $out = [];
        foreach (['color', 'Color', 'colour'] as $key) {
            $value = trim((string) ($values[$key] ?? ''));
            if ($value !== '') {
                $out[] = $value;
            }
        }
        $map = [
            'BLK' => 'Black', 'BLACK' => 'Black', 'WHT' => 'White', 'WHITE' => 'White',
            'GLD' => 'Gold', 'GOLD' => 'Gold', 'RED' => 'Red', 'BLU' => 'Blue',
            'BLUE' => 'Blue', 'GRN' => 'Green', 'SLV' => 'Silver', 'SILVER' => 'Silver',
            'WD' => 'Wood', 'WOOD' => 'Wood',
        ];
        foreach (preg_split('/[\s\-_]+/', strtoupper($sku)) ?: [] as $part) {
            if (isset($map[$part])) {
                $out[] = $map[$part];
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return array<string, mixed>
     */
    private function packageSize(ProductMaster $product, string $sku): array
    {
        $values = $product->Values;
        if (is_string($values)) {
            $decoded = json_decode($values, true);
            $values = is_array($decoded) ? $decoded : [];
        }
        $values = is_array($values) ? $values : [];
        $num = function (array $src, string ...$keys) {
            foreach ($keys as $key) {
                if (! isset($src[$key]) || ! is_numeric($src[$key]) || (float) $src[$key] <= 0) {
                    continue;
                }

                return (float) $src[$key];
            }

            return null;
        };

        $pkg = [
            'length_in' => $num($values, 'l', 'l_decl'),
            'width_in' => $num($values, 'w', 'w_decl'),
            'height_in' => $num($values, 'h', 'h_decl'),
            'weight_lb' => $num($values, 'wt_act', 'itm_wt_gw', 'wt_decl'),
            'weight_kg' => $num($values, 'wt_act_kg'),
        ];
        if (($pkg['weight_lb'] ?? null) === null && ($pkg['weight_kg'] ?? null) !== null) {
            $pkg['weight_lb'] = (float) $pkg['weight_kg'] / 0.45359237;
        }

        return $pkg;
    }

    private function groupKey(ProductMaster $product): string
    {
        $parent = trim((string) ($product->parent ?? ''));

        return $parent !== '' ? $parent : trim((string) $product->sku);
    }

    private function normalizeSkuKey(string $sku): string
    {
        $sku = strtoupper(trim(str_replace("\u{00a0}", ' ', $sku)));
        $sku = preg_replace('/\s+/u', ' ', $sku) ?? $sku;

        return $sku;
    }

    /**
     * @param  list<string>  $skus
     * @return list<string>
     */
    private function uniqueSkus(array $skus): array
    {
        $out = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku !== '' && ! in_array($sku, $out, true)) {
                $out[] = $sku;
            }
        }

        return $out;
    }

    private function forgetListingCaches(): void
    {
        try {
            Cache::forget(ListingChannelCounts::TOTAL_CACHE_KEY);
            Cache::forget('listing_channel_counts_v1:wayfair');
            app(WayfairLiveListingsService::class)->clearCache();
        } catch (\Throwable) {
        }
    }
}
