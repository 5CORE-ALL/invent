<?php

namespace App\Console\Commands;

use App\Models\EbayMetric;
use App\Models\ProductMaster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class FetchEbayListingStatus extends Command
{
    protected $signature = 'ebay:fetch-listing-status';
    protected $description = 'Fetch listing status and titles for all SKUs from eBay API and store in ebay_metrics table';

    public function handle()
    {
        $this->info('Starting eBay listing status and title fetch (GetMyeBaySelling - Complete)...');

        // Get access token
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            $this->error('Failed to get eBay access token');
            return 1;
        }

        $allListings = [];
        
        // Step 1: Fetch all ACTIVE listings
        $this->info('Fetching active listings from eBay...');
        $activeListings = $this->fetchListingsByStatus($accessToken, 'Active');
        foreach ($activeListings as $item) {
            $allListings[$item['sku']] = [
                'status' => 'ACTIVE', 
                'title' => $item['title'],
                'item_id' => $item['item_id'],
                'ebay_link' => $item['ebay_link']
            ];
        }
        $this->info("Found " . count($activeListings) . " active listings");
        
        // Step 2: Fetch all UNSOLD listings (ended without sale)
        $this->info('Fetching unsold listings from eBay...');
        $unsoldListings = $this->fetchListingsByStatus($accessToken, 'Unsold');
        foreach ($unsoldListings as $item) {
            if (!isset($allListings[$item['sku']])) {
                $allListings[$item['sku']] = [
                    'status' => 'INACTIVE', 
                    'title' => $item['title'],
                    'item_id' => $item['item_id'],
                    'ebay_link' => $item['ebay_link']
                ];
            }
        }
        $this->info("Found " . count($unsoldListings) . " unsold listings");
        
        // Step 3: Fetch all SOLD listings
        $this->info('Fetching sold listings from eBay...');
        $soldListings = $this->fetchListingsByStatus($accessToken, 'Sold');
        foreach ($soldListings as $item) {
            if (!isset($allListings[$item['sku']])) {
                $allListings[$item['sku']] = [
                    'status' => 'INACTIVE', 
                    'title' => $item['title'],
                    'item_id' => $item['item_id'],
                    'ebay_link' => $item['ebay_link']
                ];
            }
        }
        $this->info("Found " . count($soldListings) . " sold listings");
        
        // Step 4: Get all SKUs from ebay_metrics and mark remaining as MISSING
        $allMetricSkus = EbayMetric::whereNotNull('sku')
            ->where('sku', '!=', '')
            ->where('sku', 'NOT LIKE', '%PARENT%')
            ->pluck('sku')
            ->unique();

        foreach ($allMetricSkus as $sku) {
            if (!isset($allListings[$sku])) {
                $allListings[$sku] = [
                    'status' => 'MISSING', 
                    'title' => null,
                    'item_id' => null,
                    'ebay_link' => null
                ];
            }
        }

        // Bulk update database
        $this->info("\nUpdating database with " . count($allListings) . " records...");
        
        if (!empty($allListings)) {
            // Split into chunks to avoid SQL query size limits
            $listingsArray = [];
            foreach ($allListings as $sku => $data) {
                $listingsArray[] = [
                    'sku' => $sku, 
                    'listing_status' => $data['status'],
                    'ebay_title' => $data['title'],
                    'item_id' => $data['item_id'],
                    'ebay_link' => $data['ebay_link']
                ];
            }
            
            $chunks = array_chunk($listingsArray, 500, true);
            
            foreach ($chunks as $chunk) {
                $statusCases = [];
                $titleCases = [];
                $itemIdCases = [];
                $linkCases = [];
                $skuList = [];
                
                foreach ($chunk as $data) {
                    $sku = addslashes($data['sku']);
                    $status = $data['listing_status'];
                    $title = $data['ebay_title'] ? addslashes($data['ebay_title']) : '';
                    $itemId = $data['item_id'] ?? '';
                    $link = $data['ebay_link'] ? addslashes($data['ebay_link']) : '';
                    
                    $statusCases[] = "WHEN '{$sku}' THEN '{$status}'";
                    $titleCases[] = "WHEN '{$sku}' THEN '{$title}'";
                    $itemIdCases[] = "WHEN '{$sku}' THEN '{$itemId}'";
                    $linkCases[] = "WHEN '{$sku}' THEN '{$link}'";
                    $skuList[] = "'{$sku}'";
                }
                
                if (!empty($statusCases)) {
                    $statusCaseSql = implode(' ', $statusCases);
                    $titleCaseSql = implode(' ', $titleCases);
                    $itemIdCaseSql = implode(' ', $itemIdCases);
                    $linkCaseSql = implode(' ', $linkCases);
                    $skuListSql = implode(',', $skuList);
                    
                    DB::statement("
                        UPDATE ebay_metrics 
                        SET listing_status = CASE sku {$statusCaseSql} END,
                            ebay_title = CASE sku {$titleCaseSql} END,
                            item_id = CASE sku {$itemIdCaseSql} END,
                            ebay_link = CASE sku {$linkCaseSql} END,
                            updated_at = NOW()
                        WHERE sku IN ({$skuListSql})
                    ");
                }
            }
            
            $this->info("✓ Database updated successfully!");
        }

        $statuses = array_column($allListings, 'status');
        $activeCount = array_count_values($statuses)['ACTIVE'] ?? 0;
        $inactiveCount = array_count_values($statuses)['INACTIVE'] ?? 0;
        $missingCount = array_count_values($statuses)['MISSING'] ?? 0;

        $this->info("\n=== Summary ===");
        $this->info("Total SKUs updated: " . count($allListings));
        $this->info("ACTIVE: {$activeCount}");
        $this->info("INACTIVE: {$inactiveCount}");
        $this->info("MISSING: {$missingCount}");

        return 0;
    }

    private function fetchListingsByStatus($accessToken, $listType)
    {
        $allItems = [];
        $pageNumber = 1;
        $totalPages = 1;
        
        do {
            try {
                $xmlBody = '<?xml version="1.0" encoding="utf-8"?>
                    <GetMyeBaySellingRequest xmlns="urn:ebay:apis:eBLBaseComponents">';
                
                if ($listType === 'Active') {
                    $xmlBody .= '<ActiveList>
                            <Include>true</Include>
                            <Pagination>
                                <EntriesPerPage>200</EntriesPerPage>
                                <PageNumber>' . $pageNumber . '</PageNumber>
                            </Pagination>
                        </ActiveList>
                        <OutputSelector>ActiveList.ItemArray.Item.ItemID</OutputSelector>
                        <OutputSelector>ActiveList.ItemArray.Item.SKU</OutputSelector>
                        <OutputSelector>ActiveList.ItemArray.Item.Title</OutputSelector>
                        <OutputSelector>ActiveList.PaginationResult</OutputSelector>';
                } elseif ($listType === 'Unsold') {
                    $xmlBody .= '<UnsoldList>
                            <Include>true</Include>
                            <Pagination>
                                <EntriesPerPage>200</EntriesPerPage>
                                <PageNumber>' . $pageNumber . '</PageNumber>
                            </Pagination>
                        </UnsoldList>
                        <OutputSelector>UnsoldList.ItemArray.Item.ItemID</OutputSelector>
                        <OutputSelector>UnsoldList.ItemArray.Item.SKU</OutputSelector>
                        <OutputSelector>UnsoldList.ItemArray.Item.Title</OutputSelector>
                        <OutputSelector>UnsoldList.PaginationResult</OutputSelector>';
                } elseif ($listType === 'Sold') {
                    $xmlBody .= '<SoldList>
                            <Include>true</Include>
                            <Pagination>
                                <EntriesPerPage>200</EntriesPerPage>
                                <PageNumber>' . $pageNumber . '</PageNumber>
                            </Pagination>
                        </SoldList>
                        <OutputSelector>SoldList.ItemArray.Item.ItemID</OutputSelector>
                        <OutputSelector>SoldList.ItemArray.Item.SKU</OutputSelector>
                        <OutputSelector>SoldList.ItemArray.Item.Title</OutputSelector>
                        <OutputSelector>SoldList.PaginationResult</OutputSelector>';
                }
                
                $xmlBody .= '</GetMyeBaySellingRequest>';

                $response = Http::timeout(60)
                    ->withHeaders([
                        'X-EBAY-API-SITEID' => '0',
                        'X-EBAY-API-COMPATIBILITY-LEVEL' => '967',
                        'X-EBAY-API-CALL-NAME' => 'GetMyeBaySelling',
                        'X-EBAY-API-IAF-TOKEN' => $accessToken,
                    ])
                    ->withBody($xmlBody, 'text/xml')
                    ->post('https://api.ebay.com/ws/api.dll');

                if (!$response->successful()) {
                    $this->error("API request failed for {$listType}");
                    break;
                }

                $xml = simplexml_load_string($response->body());
                
                if (isset($xml->Errors)) {
                    $this->error('eBay API error: ' . (string)$xml->Errors->LongMessage);
                    break;
                }

                // Get the appropriate list node
                $listNode = null;
                if ($listType === 'Active' && isset($xml->ActiveList)) {
                    $listNode = $xml->ActiveList;
                } elseif ($listType === 'Unsold' && isset($xml->UnsoldList)) {
                    $listNode = $xml->UnsoldList;
                } elseif ($listType === 'Sold' && isset($xml->SoldList)) {
                    $listNode = $xml->SoldList;
                }

                // Get total pages from first response
                if ($pageNumber == 1 && $listNode && isset($listNode->PaginationResult->TotalNumberOfPages)) {
                    $totalPages = (int) $listNode->PaginationResult->TotalNumberOfPages;
                    $totalEntries = (int) $listNode->PaginationResult->TotalNumberOfEntries;
                    $this->info("{$listType} listings: {$totalEntries} across {$totalPages} pages");
                }

                // Process items
                if ($listNode && isset($listNode->ItemArray->Item)) {
                    foreach ($listNode->ItemArray->Item as $item) {
                        $sku = (string) ($item->SKU ?? '');
                        $title = (string) ($item->Title ?? '');
                        $itemId = (string) ($item->ItemID ?? '');
                        
                        // Clean the title to remove non-printable characters
                        $title = preg_replace('/[^\x20-\x7E]/', '', trim($title));
                        
                        // Create eBay product link
                        $ebayLink = $itemId ? "https://www.ebay.com/itm/{$itemId}" : null;
                        
                        if ($sku && !stripos($sku, 'PARENT')) {
                            $allItems[] = [
                                'sku' => $sku,
                                'title' => $title,
                                'item_id' => $itemId,
                                'ebay_link' => $ebayLink
                            ];
                        }
                    }
                }

                $pageNumber++;
                
            } catch (\Exception $e) {
                $this->error("Error fetching {$listType}: " . $e->getMessage());
                Log::error('eBay fetch error', ['type' => $listType, 'error' => $e->getMessage()]);
                break;
            }
            
        } while ($pageNumber <= $totalPages);

        return $allItems;
    }

    private function getAccessToken()
    {
        $appId = env('EBAY_APP_ID');
        $certId = env('EBAY_CERT_ID');
        $refreshToken = trim(env('EBAY_REFRESH_TOKEN'), '"'); // Remove quotes if present

        if (!$appId || !$certId || !$refreshToken) {
            $this->error('Missing eBay credentials in .env file');
            $this->info("APP_ID: " . ($appId ? 'present' : 'missing'));
            $this->info("CERT_ID: " . ($certId ? 'present' : 'missing'));
            $this->info("REFRESH_TOKEN: " . ($refreshToken ? 'present' : 'missing'));
            return null;
        }

        $this->info("Attempting to get eBay access token...");

        try {
            $response = Http::asForm()
                ->withBasicAuth($appId, $certId)
                ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ]);

            if ($response->successful()) {
                $this->info("✓ Token retrieved successfully");
                return $response->json()['access_token'] ?? null;
            }

            $this->error('Token request failed: ' . $response->body());
            $this->error('Status code: ' . $response->status());
            return null;

        } catch (\Exception $e) {
            $this->error('Token request exception: ' . $e->getMessage());
            Log::error('eBay token error', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
