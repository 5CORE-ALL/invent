<?php

namespace App\Http\Controllers\MarketingMaster;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\AmazonDataView;
use App\Models\EbayDataView;
use App\Models\ListingAuditMaster;
use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Response;


class ListingAuditMasterController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('marketing-masters.listing-audit-master');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    // Amazon SKU-level audit + store issue counts
    public function storeListingAuditAmazonData(Request $request)
    {
          //  Get link from Google Sheet
        $channelSheetResponse = (new ApiController)->fetchDataFromChannelMasterGoogleSheet();
        $channelSheet = $channelSheetResponse->getData(true)['data'] ?? [];

        $amazonRow = collect($channelSheet)->first(function ($row) {
            return strtolower(trim($row['Channel '] ?? '')) === 'amazon';
        });

        $amazonLink = $amazonRow['URL LINK'] ?? null;

        $productMasters = ProductMaster::all();
        $skus = $productMasters->pluck('sku')->unique()->toArray();
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $amazonDataViewValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $processedData = $productMasters->map(function ($item) use ($shopifyData, $amazonDataViewValues) {
            $childSku = strtoupper(trim($item->sku));
            $parent = $item->parent ?? '';
            $isParent = stripos($childSku, 'PARENT') !== false;

            $item->INV = $shopifyData[$childSku]->inv ?? 0;
            $item->L30 = $shopifyData[$childSku]->quantity ?? 0;
            $item->Parent = $parent;
            $item->is_parent = $isParent;

            // Default flags
            $item->Listed = false;
            $item->Live = false;
            $item->Category = false;
            $item->AttrFilled = false;
            $item->APlus = false;
            $item->Video = false;
            $item->Title = '';
            $item->Images = false;
            $item->Description = '';
            $item->BulletPoints = '';
            $item->InVariation = false;

            if ($childSku && isset($amazonDataViewValues[$childSku])) {
                $raw = $amazonDataViewValues[$childSku];
                $data = is_array($raw) ? $raw : (json_decode($raw, true) ?: []);
                $item->Listed = filter_var($data['Listed'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $item->Live = filter_var($data['Live'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $item->Category = filter_var($data['Category'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $item->AttrFilled = filter_var($data['AttrFilled'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $item->APlus = filter_var($data['APlus'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $item->Video = filter_var($data['Video'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $item->Title = $data['Title'] ?? '';
                $item->Images = filter_var($data['Images'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $item->Description = $data['Description'] ?? '';
                $item->BulletPoints = $data['BulletPoints'] ?? '';
                $item->InVariation = filter_var($data['InVariation'] ?? false, FILTER_VALIDATE_BOOLEAN);
            }

            return $item;
        })->values();

        // Filter valid SKUs
        $filtered = $processedData->filter(fn($item) => !$item->is_parent && ($item->INV != 0));
        $total = $filtered->count();

        // Count issues
        $not_listed       = $total - $filtered->where('Listed', true)->count();
        $not_live         = $total - $filtered->where('Live', true)->count();
        $category_issue   = $total - $filtered->where('Category', true)->count();
        $attr_not_filled  = $total - $filtered->where('AttrFilled', true)->count();
        $a_plus_issue     = $total - $filtered->where('APlus', true)->count();
        $video_issue      = $total - $filtered->where('Video', true)->count();
        $title_issue      = $filtered->filter(fn($x) => trim($x->Title) == '')->count();
        $images           = $total - $filtered->where('Images', true)->count();
        $description      = $filtered->filter(fn($x) => trim($x->Description) == '')->count();
        $bullet_points    = $filtered->filter(fn($x) => trim($x->BulletPoints) == '')->count();

        // Store in DB
        $listingAuditData = ListingAuditMaster::updateOrInsert(
            ['channel' => 'AMAZON'],
            [
                'link' => $amazonLink,
                'not_listed' => $not_listed,
                'not_live' => $not_live,
                'category_issue' => $category_issue,
                'attr_not_filled' => $attr_not_filled,
                'a_plus_issue' => $a_plus_issue,
                'video_issue' => $video_issue,
                'title_issue' => $title_issue,
                'images' => $images,
                'description' => $description,
                'bullet_points' => $bullet_points,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json([
            'data' => $processedData,
            'status' => 200,
        ]);
    }

    // Summary for datatable (Amazon, eBay, Doba etc.)
    public function getListingAuditSummaryWithChannelInfo()
    {
        // Step 1: Fetch sheet data
        $channelSheetResponse = (new ApiController)->fetchDataFromChannelMasterGoogleSheet();
        $channelSheet = $channelSheetResponse->getData(true)['data'] ?? [];

        // Step 2: Load audit DB data and key by lowercase channel
        $auditData = ListingAuditMaster::get()->keyBy(fn($row) => strtolower(trim($row->channel)));

        $finalData = [];

        foreach ($channelSheet as $index => $item) {
            // ✅ Always sanitize channel name
            $channelName = trim($item['Channel '] ?? '');
            if (!$channelName) continue; // Skip rows with empty channel

            $lowerChannel = strtolower($channelName);
            $audit = $auditData[$lowerChannel] ?? null;

            $finalData[] = [
                'sl'              => $index + 1,
                'Channel '        => $channelName,
                'URL LINK'        => trim($item['URL LINK'] ?? ''),
                'R&A'             => trim($item['R&A'] ?? ''),

                // Database values
                'not_listed'      => $audit->not_listed ?? 0,
                'not_live'        => $audit->not_live ?? 0,
                'category_issue'  => $audit->category_issue ?? 0,
                'attr_not_filled' => $audit->attr_not_filled ?? 0,
                'a_plus_issue'    => $audit->a_plus_issue ?? 0,
                'video_issue'     => $audit->video_issue ?? 0,
                'title_issue'     => $audit->title_issue ?? 0,
                'images'          => $audit->images ?? 0,
                'description'     => $audit->description ?? 0,
                'bullet_points'   => $audit->bullet_points ?? 0,
            ];
        }

        return response()->json([
            'data' => array_values($finalData), // ✅ Ensure zero-based array
            'status' => 200,
        ]);
    }


    // eBay SKU-level audit + store issue counts
    public function storeListingAuditEbayData(Request $request)
    {
        // Step 1: Get link from Channel Sheet for eBay
        $channelSheetResponse = (new ApiController)->fetchDataFromChannelMasterGoogleSheet();
        $channelSheet = $channelSheetResponse->getData(true)['data'] ?? [];

        $ebayRow = collect($channelSheet)->first(function ($row) {
            return strtolower(trim($row['Channel '] ?? '')) === 'ebay';
        });

        $ebayLink = $ebayRow['URL LINK'] ?? null;

        // Step 2: Load product, Shopify & eBay audit data
        $productMasters = ProductMaster::all();
        $skus = $productMasters->pluck('sku')->unique()->toArray();
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $ebayDataViewValues = EbayDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $processedData = $productMasters->map(function ($item) use ($shopifyData, $ebayDataViewValues) {
            $childSku = strtoupper(trim($item->sku));
            $parent = $item->parent ?? '';
            $isParent = stripos($childSku, 'PARENT') !== false;

            $item->INV = $shopifyData[$childSku]->inv ?? 0;
            $item->L30 = $shopifyData[$childSku]->quantity ?? 0;
            $item->Parent = $parent;
            $item->is_parent = $isParent;

            // Default values
            $item->Listed = false;
            $item->Live = false;
            $item->Category = false;
            $item->AttrFilled = false;
            $item->APlus = false;
            $item->Video = false;
            $item->Title = '';
            $item->Images = false;
            $item->Description = '';
            $item->BulletPoints = '';
            $item->InVariation = false;

            if ($childSku && isset($ebayDataViewValues[$childSku])) {
                $raw = $ebayDataViewValues[$childSku];
                $data = is_array($raw) ? $raw : (json_decode($raw, true) ?: []);
                $item->Listed = filter_var($data['Listed'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $item->Live = filter_var($data['Live'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $item->Category = filter_var($data['Category'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $item->AttrFilled = filter_var($data['AttrFilled'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $item->APlus = filter_var($data['APlus'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $item->Video = filter_var($data['Video'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $item->Title = $data['Title'] ?? '';
                $item->Images = filter_var($data['Images'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $item->Description = $data['Description'] ?? '';
                $item->BulletPoints = $data['BulletPoints'] ?? '';
                $item->InVariation = filter_var($data['InVariation'] ?? false, FILTER_VALIDATE_BOOLEAN);
            }

            return $item;
        })->values();

        // Step 3: Filter valid SKUs
        $filtered = $processedData->filter(fn($item) => !$item->is_parent && ($item->INV != 0));
        $total = $filtered->count();

        // Step 4: Count issues
        $not_listed       = $total - $filtered->where('Listed', true)->count();
        $not_live         = $total - $filtered->where('Live', true)->count();
        $category_issue   = $total - $filtered->where('Category', true)->count();
        $attr_not_filled  = $total - $filtered->where('AttrFilled', true)->count();
        $a_plus_issue     = $total - $filtered->where('APlus', true)->count();
        $video_issue      = $total - $filtered->where('Video', true)->count();
        $title_issue      = $filtered->filter(fn($x) => trim($x->Title) == '')->count();
        $images           = $total - $filtered->where('Images', true)->count();
        $description      = $filtered->filter(fn($x) => trim($x->Description) == '')->count();
        $bullet_points    = $filtered->filter(fn($x) => trim($x->BulletPoints) == '')->count();

        // Step 5: Store into DB (eBay row)
        ListingAuditMaster::updateOrInsert(
            ['channel' => 'EBAY'],
            [
                'link' => $ebayLink,
                'not_listed' => $not_listed,
                'not_live' => $not_live,
                'category_issue' => $category_issue,
                'attr_not_filled' => $attr_not_filled,
                'a_plus_issue' => $a_plus_issue,
                'video_issue' => $video_issue,
                'title_issue' => $title_issue,
                'images' => $images,
                'description' => $description,
                'bullet_points' => $bullet_points,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json([
            'data' => $processedData,
            'status' => 200,
        ]);
    }


    public function exportListingAuditCSV()
    {
        $data = ListingAuditMaster::all();

        // Column headers
        $headers = [
            'Channel', 'Link', 'Not Listed', 'Not Live', 'Category Issue',
            'Attr Not Filled', 'A+ Issue', 'Video Issue', 'Title Issue',
            'Images Issue', 'Description Issue', 'Bullet Points Issue',
        ];

        // Open memory "file"
        $callback = function () use ($data, $headers) {
            $file = fopen('php://output', 'w');

            // Write column headers
            fputcsv($file, $headers);

            // Write data rows
            foreach ($data as $row) {
                fputcsv($file, [
                    $row->channel,
                    $row->link,
                    $row->not_listed,
                    $row->not_live,
                    $row->category_issue,
                    $row->attr_not_filled,
                    $row->a_plus_issue,
                    $row->video_issue,
                    $row->title_issue,
                    $row->images,
                    $row->description,
                    $row->bullet_points,
                ]);
            }

            fclose($file);
        };

        $filename = 'listing_audit_report_' . now()->format('Ymd_His') . '.csv';

        return Response::stream($callback, 200, [
            "Content-Type" => "text/csv",
            "Content-Disposition" => "attachment; filename={$filename}"
        ]);
    }


    public function getAuditMasterTableData()
    {
        $data = ListingAuditMaster::orderBy('channel')->get();
        return response()->json(['data' => $data]);
    }


}
