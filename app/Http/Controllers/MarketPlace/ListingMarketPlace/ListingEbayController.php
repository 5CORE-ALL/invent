<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;



use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductMaster\TechnicalSpecificationsController;
use App\Support\Marketplace\AutomatedListingPage;
use App\Support\Marketplace\ChannelListingRegistry;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\EbayDataView;
use App\Models\EbayMetric;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;


class ListingEbayController extends Controller
{
    use HandlesListingPublishActions;

    protected function listingPublishChannel(): string
    {
        return 'ebay';
    }

    public function listingEbay(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        $percentage = Cache::remember('ebay_marketplace_percentage', now()->addDays(30), function () {
            return 100;
        });

        return view('market-places.listing-market-places.listingEbay', [
            'mode' => $mode,
            'demo' => $demo,
            'ebayPercentage' => $percentage
        ]);
    }

    public function getViewListingEbayData(Request $request)
    {
        return response()->json([
            'status' => 200,
            'data' => AutomatedListingPage::rows('ebay'),
        ]);
    }

    /**
     * GET /listing_ebay/field-counts
     * Per SKU, from the data in our app (Technical Specifications and the eBay category of Category Master):
     *   m = [fields with data, mandatory fields]  (the eBay required fields of the SKU's category)
     *   a = [fields with data, all fields]        (required fields, every field saved in the category, UPC)
     * Fields crossed out for a category on Technical Specifications are left out of both counts.
     */
    public function getFieldCounts(Request $request)
    {
        try {
            @set_time_limit(300);
            @ini_set('memory_limit', '512M');

            $json = app(TechnicalSpecificationsController::class)->getData($request)->getData(true);
            if (($json['status'] ?? 500) !== 200) {
                return response()->json(['status' => 500, 'message' => (string) ($json['error'] ?? $json['message'] ?? 'Could not load the fields.')], 500);
            }

            $hidden = [];
            foreach ((array) ($json['hidden_fields'] ?? []) as $scope => $keys) {
                $hidden[(string) $scope] = array_flip(array_map('strval', (array) $keys));
            }
            $sources = [];
            foreach ((array) ($json['column_sources'] ?? []) as $map) {
                $column = $this->specKey((string) ($map['column'] ?? ''));
                if ($column !== '') {
                    $sources[$column] = (string) ($map['source'] ?? '');
                }
            }

            $out = [];
            foreach ((array) ($json['data'] ?? []) as $row) {
                $sku = trim((string) ($row['SKU'] ?? ''));
                if ($sku === '' || stripos($sku, 'PARENT ') === 0) {
                    continue;
                }
                $skipped = $hidden[(string) ($row['hide_scope'] ?? '')] ?? [];
                $mandatory = $this->countFields((array) ($row['ebay_required_only'] ?? []), $row, $skipped, $sources);
                $all = $this->countFields((array) ($row['ebay_required'] ?? []), $row, $skipped, $sources);
                $out[strtoupper(trim(str_replace("\u{00a0}", ' ', $sku)))] = ['m' => $mandatory, 'a' => $all, 'cat' => trim((string) ($row['category_ebay'] ?? ''))];
            }

            // i = images saved in Product Master (Image Master counts the same way: image1..image20, else main_image).
            try {
                $imageCols = array_merge(['sku', 'main_image'], array_map(static fn ($n) => 'image'.$n, range(1, 20)));
                ProductMaster::query()->select($imageCols)->whereIn('sku', $out === [] ? [''] : array_map('strval', array_keys($out)))->get()->each(function ($pm) use (&$out) {
                    $count = 0;
                    for ($n = 1; $n <= 20; $n++) {
                        if (trim((string) ($pm->{'image'.$n} ?? '')) !== '') {
                            $count++;
                        }
                    }
                    if ($count === 0 && trim((string) $pm->main_image) !== '') {
                        $count = 1;
                    }
                    $key = strtoupper(trim(str_replace("\u{00a0}", ' ', (string) $pm->sku)));
                    if (isset($out[$key])) {
                        $out[$key]['i'] = $count;
                    }
                });
            } catch (\Throwable $e) {
                Log::warning('ListingEbay: image counts failed', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'status' => 200,
                'data' => $out,
                'groups' => array_values((array) ($json['column_groups'] ?? [])),
            ]);
        } catch (\Throwable $e) {
            Log::error('ListingEbay: field counts failed', ['error' => $e->getMessage()]);

            return response()->json(['status' => 500, 'message' => 'Could not count the fields: '.$e->getMessage()], 500);
        }
    }

    /**
     * @param  array<int, mixed>  $names
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $skipped
     * @param  array<string, string>  $sources
     * @return array{0: int, 1: int, 2: list<string>}  [with data, total, names without data]
     */
    private function countFields(array $names, array $row, array $skipped, array $sources): array
    {
        $seen = [];
        $have = 0;
        $missing = [];
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '' || strlen($name) > 120 || preg_match('/[{};]|text-decoration/', $name)) {
                continue;
            }
            $identity = $this->specIdentity($name);
            if (isset($seen[$identity]) || isset($skipped[$identity])) {
                continue;
            }
            $seen[$identity] = true;
            if ($this->fieldHasData($row, $name, $sources)) {
                $have++;
            } else {
                $missing[] = $name;
            }
        }

        return [$have, count($seen), $missing];
    }

    /**
     * Same rules as the Technical Specifications grid: fixed values, MPN and Model = SKU, UPC from CP Master/Amazon,
     * columns that follow a source column, then what is saved for the SKU.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $sources
     */
    private function fieldHasData(array $row, string $name, array $sources): bool
    {
        $fixed = $this->fixedFieldValue($row, $name);
        if ($fixed !== null) {
            return $fixed !== '';
        }
        $source = $sources[$this->specKey($name)] ?? '';
        if ($source !== '') {
            $own = $this->fixedFieldValue($row, $source);

            return trim((string) ($own ?? $this->savedFieldValue($row, $source))) !== '';
        }
        if ($this->savedFieldValue($row, $name) !== '') {
            return true;
        }
        $values = (array) ($row['ebay_values'] ?? []);

        return trim((string) ($values[$name] ?? '')) !== '';
    }

    private function fixedFieldValue(array $row, string $name): ?string
    {
        $key = $this->specKey($name);
        $fixed = ['brand' => '5 Core', 'manufacturer' => '5 Core', 'condition' => 'New', 'countryoforigin' => 'China', 'durabilityguarantee' => '30'];
        if (isset($fixed[$key])) {
            return $fixed[$key];
        }
        if ($key === 'mpn' || $key === 'model') {
            $sku = trim((string) ($row['SKU'] ?? ''));

            return $sku === '' || stripos($sku, 'PARENT ') === 0 ? null : $sku;
        }
        if ($key === 'upc') {
            $upc = trim((string) ($row['upc'] ?? ''));

            return $upc !== '' ? $upc : null;
        }

        return null;
    }

    private function savedFieldValue(array $row, string $name): string
    {
        $want = $this->specIdentity($name);
        foreach ((array) ($row['description_v2_specifications'] ?? []) as $spec) {
            if (is_array($spec) && $this->specIdentity((string) ($spec['key'] ?? '')) === $want && trim((string) ($spec['value'] ?? '')) !== '') {
                return trim((string) $spec['value']);
            }
        }

        return '';
    }

    private function specKey(string $name): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', $name) ?? $name);
    }

    private function specIdentity(string $name): string
    {
        $key = $this->specKey(trim($name));
        if ($key === '') {
            $key = strtolower(trim($name));
        }
        $canon = [
            'maxpower' => 'maxpower', 'pmpo' => 'maxpower', 'pmpopower' => 'maxpower', 'peakpower' => 'maxpower', 'powercapacity' => 'maxpower',
            'rmspower' => 'rmspower', 'rms' => 'rmspower',
        ];

        return $canon[$key] ?? $key;
    }

    /**
     * "Create draft": add this SKU to the Listing Manager channel drafts for eBay. Nothing is sent to eBay.
     * An existing draft is left alone so edits made in Listing Manager are never overwritten.
     */
    public function createDraft(Request $request)
    {
        $sku = trim((string) $request->input('sku', ''));
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required.'], 422);
        }
        $channelId = $this->ebayChannelId();
        if ($channelId === null) {
            return response()->json(['success' => false, 'message' => 'The eBay channel was not found in Channel Master.'], 422);
        }

        $draft = $this->findEbayDraft($channelId, $sku);
        $wasReset = false;
        if ($draft && $draft->status === 'listed') {
            // The draft says listed, but the SKU is on the Missing L tab: the listing was ended or deleted on eBay.
            if ($this->isLiveOnEbay($sku)) {
                return response()->json(['success' => true, 'message' => $sku.' is already listed on eBay.', 'draft_id' => $draft->id, 'status' => 'listed']);
            }
            $this->resetStaleDraft($draft);
            $draft->refresh();
            $wasReset = true;
        }
        if ($draft) {
            return response()->json([
                'success' => true,
                'message' => $wasReset
                    ? 'The old eBay listing of '.$sku.' no longer exists, so its draft was moved back to Drafts and the old item id was cleared ('.$draft->status.'). Use Publish to update it and publish.'
                    : 'A draft for '.$sku.' already exists ('.$draft->status.'). Use Publish to update it with the latest data and publish.',
                'draft_id' => $draft->id,
                'status' => (string) $draft->status,
            ]);
        }

        $body = $this->addEbayDraft($channelId, $sku);
        $draft = $this->findEbayDraft($channelId, $sku);
        if (! $draft) {
            return response()->json([
                'success' => false,
                'message' => 'No draft was created for '.$sku.'. There is no title, image or price data to build it from.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Draft created for '.$sku.' ('.$draft->status.'). Nothing was sent to eBay yet; use Publish when it is ready.',
            'draft_id' => $draft->id,
            'status' => (string) $draft->status,
            'detail' => $body['message'] ?? '',
        ]);
    }

    /**
     * "Publish": update the draft with the latest Product Master data (creating it when missing), then publish it.
     */
    public function publishDraft(Request $request)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
        $sku = trim((string) $request->input('sku', ''));
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required.'], 422);
        }
        $channelId = $this->ebayChannelId();
        if ($channelId === null) {
            return response()->json(['success' => false, 'message' => 'The eBay channel was not found in Channel Master.'], 422);
        }

        try {
            $manager = app(\App\Http\Controllers\MarketPlace\ListingManagerController::class);
            $draft = $this->findEbayDraft($channelId, $sku);
            if ($draft && $draft->status === 'listed' && ! $this->isLiveOnEbay($sku)) {
                // Ended or deleted on eBay: the stale "listed" draft would otherwise answer "already published".
                $this->resetStaleDraft($draft);
                $draft->refresh();
            }
            if (! $draft) {
                $this->addEbayDraft($channelId, $sku);
                $draft = $this->findEbayDraft($channelId, $sku);
            } elseif ($draft->status !== 'listed') {
                // Update the draft from the masters; category and item specifics already on the draft are kept.
                foreach (['title', 'description', 'images', 'bullets', 'identifiers', 'pricing'] as $source) {
                    try {
                        $manager->loadDraftFromMaster(Request::create('/', 'POST', ['source' => $source]), (int) $draft->id);
                    } catch (\Throwable $e) {
                        Log::warning('listing_ebay draft update skipped a source', ['sku' => $sku, 'source' => $source, 'error' => $e->getMessage()]);
                    }
                }
            }
            if (! $draft) {
                return response()->json([
                    'success' => false,
                    'message' => 'No draft could be created for '.$sku.'. There is no title, image or price data to build it from.',
                ], 422);
            }

            return $manager->publishDraft(Request::create('/', 'POST'), (int) $draft->id);
        } catch (\Throwable $e) {
            Log::error('listing_ebay publishDraft failed', ['sku' => $sku, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Publish failed: '.$e->getMessage()], 500);
        }
    }

    /**
     * GET /listing_ebay/drafts: SKU (upper case) => draft status, for SKUs that already have an eBay draft
     * waiting (draft / ready / queued / failed). The Create draft column shows a green tick for them.
     */
    public function getDrafts()
    {
        $channelId = $this->ebayChannelId();
        if ($channelId === null || ! \Illuminate\Support\Facades\Schema::hasTable('listing_manager_channel_drafts')) {
            return response()->json(['status' => 200, 'data' => (object) []]);
        }

        $out = [];
        \App\Models\ListingManagerChannelDraft::query()
            ->where('channel_id', $channelId)
            ->whereIn('status', ['draft', 'ready', 'queued', 'failed'])
            ->get(['seller_sku', 'status'])
            ->each(function ($row) use (&$out) {
                $key = strtoupper(trim(str_replace("\u{00a0}", ' ', (string) $row->seller_sku)));
                if ($key !== '') {
                    $out[$key] = (string) $row->status;
                }
            });

        return response()->json(['status' => 200, 'data' => (object) $out]);
    }

    private function isLiveOnEbay(string $sku): bool
    {
        return (bool) (\App\Support\Marketplace\ListingManagerPublishStatus::check('ebay', $sku)['listed'] ?? false);
    }

    private function resetStaleDraft(\App\Models\ListingManagerChannelDraft $draft): void
    {
        $draft->loadMissing('channel:id,channel');
        \App\Support\Marketplace\EbayStaleDrafts::reset(
            $draft,
            (string) ($draft->channel->channel ?? 'eBay'),
            'eBay has no live listing for this SKU (ended or deleted)'
        );
    }

    private function ebayChannelId(): ?int
    {
        $rows = \App\Models\ChannelMaster::query()
            ->whereNotNull('channel')
            ->get(['id', 'channel', 'status']);
        $match = $rows
            ->filter(fn ($c) => \App\Support\Marketplace\ListingChannelCounts::normalize((string) $c->channel) === 'ebay')
            ->sortBy(fn ($c) => strtolower(trim((string) $c->status)) === 'active' ? 0 : 1)
            ->first();

        return $match ? (int) $match->id : null;
    }

    private function findEbayDraft(int $channelId, string $sku): ?\App\Models\ListingManagerChannelDraft
    {
        $sku = preg_replace('/\s+/u', ' ', trim($sku)) ?? trim($sku);

        return \App\Models\ListingManagerChannelDraft::query()
            ->where('channel_id', $channelId)
            ->whereRaw('UPPER(TRIM(seller_sku)) = ?', [strtoupper($sku)])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function addEbayDraft(int $channelId, string $sku): array
    {
        $response = app(\App\Http\Controllers\MarketPlace\ListingManagerController::class)->addToChannelDrafts(
            Request::create('/', 'POST', ['skus' => [$sku], 'channel_ids' => [$channelId]])
        );
        $data = method_exists($response, 'getData') ? $response->getData(true) : [];

        return is_array($data) ? $data : [];
    }

    public function updateStatus(Request $request)
    {
        if ($response = $this->listingPublishResponse($request)) {
            return $response;
        }

        $validated = $request->validate([
            'sku' => 'required|string',
            'nr_req' => 'nullable|string',
            'listed' => 'nullable|string',
            'buyer_link' => 'nullable|string',
            'seller_link' => 'nullable|string',
        ]);

        $sku = $validated['sku'];
        $ebayData = EbayDataView::where('sku', $sku)->first();

        $existing = $ebayData ? $ebayData->value : [];

        // Handle nr_req - save as NRL field in ebay_data_view
        if ($request->has('nr_req')) {
            // Map: 'NR' -> 'NRL', 'REQ' -> 'REQ'
            $existing['NRL'] = ($validated['nr_req'] === 'NR') ? 'NRL' : 'REQ';
        }

        // Handle listed field - save as Listed (capitalized) to match the JSON structure
        if ($request->has('listed')) {
            // Save to both 'Listed' (for boolean conversion) and 'listed' (for string)
            $existing['Listed'] = ($validated['listed'] === 'Listed') ? true : false;
            $existing['listed'] = $validated['listed'];
        }

        // Only update other fields that are present in the request
        $fields = ['buyer_link', 'seller_link'];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                $existing[$field] = $validated[$field];
            }
        }

        EbayDataView::updateOrCreate(
            ['sku' => $validated['sku']],
            ['value' => $existing]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Status updated successfully'
        ]);
    }

    public function getNrReqCount()
    {
        return ChannelListingRegistry::nrReqCountArray('ebay');
    }

    public function import(Request $request)
    {
        try {
            Log::info('=== Ebay CSV Import Started ===');
            
            $request->validate([
                'file' => 'required|mimes:csv,txt',
            ]);

            $file = $request->file('file');
        
            $fileContent = file($file);
        
            
            // Detect delimiter (comma or tab)
            $firstLine = $fileContent[0];
        
            
            $delimiter = (strpos($firstLine, "\t") !== false) ? "\t" : ",";
         
            
            // Parse CSV with detected delimiter
            $rows = array_map(function($line) use ($delimiter) {
                return str_getcsv($line, $delimiter);
            }, $fileContent);
            
            // Process header - remove BOM if present
            $header = array_map(function ($h) {
                return trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)); // remove BOM if present
            }, $rows[0]);
            
        

            unset($rows[0]);

            $allowedHeaders = ['sku', 'nr_req', 'listed', 'buyer_link', 'seller_link'];
            foreach ($header as $h) {
                if (!in_array($h, $allowedHeaders)) {
                
                    return response()->json([
                        'error' => "Invalid header '$h'. Allowed headers: " . implode(', ', $allowedHeaders)
                    ], 422);
                }
            }

            $processedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            foreach ($rows as $rowIndex => $row) {
                if (count($row) < 1) {
                    Log::info("Row $rowIndex: Skipped (empty row)");
                    $skippedCount++;
                    continue; // skip empty
                }

                Log::info("Row $rowIndex data: " . json_encode($row));

                // Pad row with empty strings if it has fewer columns than header
                $headerCount = count($header);
                $rowCount = count($row);
                if ($rowCount < $headerCount) {
                    $row = array_pad($row, $headerCount, '');
                   
                }
                
                // Trim row if it has more columns than header
                if ($rowCount > $headerCount) {
                    $row = array_slice($row, 0, $headerCount);
                 
                }

                $rowData = array_combine($header, $row);
              
                
                $sku = trim($rowData['sku'] ?? '');

                if (!$sku) {
                    $skippedCount++;
                    continue;
                }

                try {
                    // Only import SKUs that exist in product_masters
                    if (!ProductMaster::where('sku', $sku)->exists()) {
                     
                        $skippedCount++;
                        continue;
                    }

                    $ebayData = EbayDataView::where('sku', $sku)->first();
                    $existing = $ebayData ? $ebayData->value : [];

                    // Handle nr_req - save as NRL field
                    if (array_key_exists('nr_req', $rowData) && $rowData['nr_req'] !== '') {
                        $existing['NRL'] = (trim($rowData['nr_req']) === 'NRL') ? 'NRL' : 'REQ';
                    }

                    // Handle listed field
                    if (array_key_exists('listed', $rowData) && $rowData['listed'] !== '') {
                        $listedVal = trim($rowData['listed']);
                        $existing['Listed'] = ($listedVal === 'Listed') ? true : false;
                        $existing['listed'] = $listedVal;
                    }

                    // Handle other fields
                    $otherFields = ['buyer_link', 'seller_link'];
                    foreach ($otherFields as $field) {
                        if (array_key_exists($field, $rowData) && $rowData[$field] !== '') {
                            $existing[$field] = trim($rowData[$field]);
                        }
                    }

                    EbayDataView::updateOrCreate(
                        ['sku' => $sku],
                        ['value' => $existing]
                    );
                    
               
                    $processedCount++;
                    
                } catch (\Exception $rowError) {
               
                    $errorCount++;
                }
            }

       

            $message = 'CSV imported successfully';
            if ($errorCount > 0) {
                $message .= " (Processed: $processedCount, Skipped: $skippedCount, Errors: $errorCount)";
            }

            return response()->json([
                'success' => $message,
                'processed' => $processedCount,
                'skipped' => $skippedCount,
                'errors' => $errorCount
            ]);
            
        } catch (\Exception $e) {
            Log::error('Ebay CSV Import Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'error' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }


    public function export(Request $request)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="listing_status.csv"',
        ];

        $columns = ['sku', 'nr_req', 'listed', 'buyer_link', 'seller_link'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');

            // Write header row
            fputcsv($file, $columns);

            // Fetch all SKUs from product master
            $productMasters = ProductMaster::pluck('sku');

            foreach ($productMasters as $sku) {
                $ebayData = EbayDataView::where('sku', $sku)->first();

                // Read NRL field
                $nrlValue = $ebayData->value['NRL'] ?? null;
                $nr_req = '';
                if ($nrlValue === 'NRL') {
                    $nr_req = 'NRL';
                } else if ($nrlValue === 'REQ') {
                    $nr_req = 'REQ';
                }

                // Read listed field
                $listedValue = $ebayData->value['Listed'] ?? $ebayData->value['listed'] ?? null;
                $listed = '';
                if (is_bool($listedValue)) {
                    $listed = $listedValue ? 'Listed' : 'Pending';
                } else if (is_string($listedValue)) {
                    $listed = $listedValue;
                }

                $row = [
                    'sku'         => $sku,
                    'nr_req'      => $nr_req,
                    'listed'      => $listed,
                    'buyer_link'  => $ebayData->value['buyer_link'] ?? '',
                    'seller_link' => $ebayData->value['seller_link'] ?? '',
                ];

                fputcsv($file, $row);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}