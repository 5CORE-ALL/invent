<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Ebay2ApiService;
use App\Services\Support\EbaySellInventoryListingResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$sku = $argv[1] ?? 'GFF TP BLK';

echo "=== eBay2 debug for SKU: {$sku} ===\n\n";

foreach (['ebay_2_metrics', 'ebay2_product_sheet'] as $table) {
    if (! Schema::hasTable($table)) {
        echo "{$table}: (no table)\n";
        continue;
    }
    $rows = DB::table($table)
        ->where('sku', $sku)
        ->orWhere('sku', 'like', 'GFF%')
        ->limit(5)
        ->get();
    echo "{$table}: ".$rows->count()." row(s)\n";
    foreach ($rows as $row) {
        echo '  '.json_encode($row)."\n";
    }
}

$svc = app(Ebay2ApiService::class);
try {
    $token = $svc->generateBearerToken();
    echo "\nToken: OK (".strlen($token)." chars)\n";
} catch (Throwable $e) {
    echo "\nToken FAILED: ".$e->getMessage()."\n";
    exit(1);
}

$endpoint = $svc->getTradingEndpoint();
$headers = $svc->getTradingHeadersForResolver();

echo "\n--- Inventory offer lookup ---\n";
$inv = EbaySellInventoryListingResolver::resolveListingIdBySku($token, $sku);
echo 'listingId: '.($inv ?? '(null)')."\n";

echo "\n--- GetSellerList ---\n";
$xmlBody = '<?xml version="1.0" encoding="utf-8"?>'
    .'<GetSellerListRequest xmlns="urn:ebay:apis:eBLBaseComponents">'
    .'<RequesterCredentials><eBayAuthToken>'.htmlspecialchars($token, ENT_XML1).'</eBayAuthToken></RequesterCredentials>'
    .'<ErrorLanguage>en_US</ErrorLanguage><WarningLevel>High</WarningLevel>'
    .'<GranularityLevel>Fine</GranularityLevel><DetailLevel>ReturnAll</DetailLevel>'
    .'<SKU>'.htmlspecialchars($sku, ENT_XML1).'</SKU>'
    .'<EndTimeFrom>'.gmdate('Y-m-d\TH:i:s.000\Z', strtotime('-2 days')).'</EndTimeFrom>'
    .'<EndTimeTo>'.gmdate('Y-m-d\TH:i:s.000\Z', strtotime('+118 days')).'</EndTimeTo>'
    .'</GetSellerListRequest>';

$h = array_merge($headers, ['X-EBAY-API-CALL-NAME' => 'GetSellerList', 'Content-Type' => 'text/xml']);
$resp = Illuminate\Support\Facades\Http::withoutVerifying()->withHeaders($h)->withBody($xmlBody, 'text/xml')->timeout(45)->post($endpoint);
echo 'HTTP '.$resp->status()."\n";
$body = (string) $resp->body();
if (preg_match('/<Ack>([^<]+)<\/Ack>/', $body, $m)) {
    echo 'Ack: '.$m[1]."\n";
}
if (preg_match('/<ShortMessage>([^<]+)<\/ShortMessage>/', $body, $m)) {
    echo 'Error: '.$m[1]."\n";
}
if (preg_match('/<LongMessage>([^<]+)<\/LongMessage>/', $body, $m)) {
    echo 'Long: '.$m[1]."\n";
}
if (preg_match('/<ItemID>(\d+)<\/ItemID>/', $body, $m)) {
    echo 'ItemID: '.$m[1]."\n";
}

echo "\n--- GetSellerList paginated SKU search ---\n";
$targetUpper = strtoupper($sku);
$page = 1;
$maxPages = 25;
$foundItem = null;
while ($page <= $maxPages) {
    $xmlPage = '<?xml version="1.0" encoding="utf-8"?>'
        .'<GetSellerListRequest xmlns="urn:ebay:apis:eBLBaseComponents">'
        .'<RequesterCredentials><eBayAuthToken>'.htmlspecialchars($token, ENT_XML1).'</eBayAuthToken></RequesterCredentials>'
        .'<Pagination><EntriesPerPage>200</EntriesPerPage><PageNumber>'.$page.'</PageNumber></Pagination>'
        .'<EndTimeFrom>'.gmdate('Y-m-d\TH:i:s.000\Z', strtotime('-2 days')).'</EndTimeFrom>'
        .'<EndTimeTo>'.gmdate('Y-m-d\TH:i:s.000\Z', strtotime('+118 days')).'</EndTimeTo>'
        .'</GetSellerListRequest>';
    $respPage = Illuminate\Support\Facades\Http::withoutVerifying()->withHeaders($h)->withBody($xmlPage, 'text/xml')->timeout(60)->post($endpoint);
    $bodyPage = (string) $respPage->body();
    if (! preg_match('/<Ack>(Success|Warning)<\/Ack>/', $bodyPage)) {
        echo "Page {$page}: ack failed\n";
        break;
    }
    if (preg_match_all('/<Item>.*?<\/Item>/s', $bodyPage, $itemBlocks)) {
        foreach ($itemBlocks[0] as $block) {
            preg_match('/<SKU>([^<]*)<\/SKU>/', $block, $skuM);
            $itemSku = strtoupper(trim($skuM[1] ?? ''));
            if ($itemSku === $targetUpper) {
                preg_match('/<ItemID>(\d+)<\/ItemID>/', $block, $idM);
                $foundItem = ['item_id' => $idM[1] ?? '', 'sku' => $skuM[1] ?? ''];
                break 2;
            }
        }
    }
    if ($page === 1 && preg_match('/<TotalNumberOfPages>(\d+)<\/TotalNumberOfPages>/', $bodyPage, $tp)) {
        $maxPages = min((int) $tp[1], 25);
        echo "Total pages: {$tp[1]}\n";
    }
    $page++;
}
if ($foundItem) {
    echo 'FOUND via paginated GetSellerList: '.json_encode($foundItem)."\n";
} else {
    echo "Not found in GetSellerList (searched {$page} pages)\n";
}

echo "\n--- GetMyeBaySelling ActiveList ---\n";
$page = 1;
$foundActive = null;
while ($page <= 10) {
    $xmlActive = '<?xml version="1.0" encoding="utf-8"?>'
        .'<GetMyeBaySellingRequest xmlns="urn:ebay:apis:eBLBaseComponents">'
        .'<ActiveList><Include>true</Include>'
        .'<Pagination><EntriesPerPage>200</EntriesPerPage><PageNumber>'.$page.'</PageNumber></Pagination>'
        .'</ActiveList>'
        .'<OutputSelector>ActiveList.ItemArray.Item.ItemID</OutputSelector>'
        .'<OutputSelector>ActiveList.ItemArray.Item.SKU</OutputSelector>'
        .'<OutputSelector>ActiveList.ItemArray.Item.Title</OutputSelector>'
        .'<OutputSelector>ActiveList.ItemArray.Item.Variations</OutputSelector>'
        .'<OutputSelector>ActiveList.PaginationResult</OutputSelector>'
        .'</GetMyeBaySellingRequest>';
    $hActive = array_merge($headers, [
        'X-EBAY-API-CALL-NAME' => 'GetMyeBaySelling',
        'Content-Type' => 'text/xml',
        'X-EBAY-API-IAF-TOKEN' => $token,
    ]);
    unset($hActive['X-EBAY-API-CALL-NAME']); // reset below
    $hActive['X-EBAY-API-CALL-NAME'] = 'GetMyeBaySelling';
    $respActive = Illuminate\Support\Facades\Http::withoutVerifying()->withHeaders($hActive)->withBody($xmlActive, 'text/xml')->timeout(60)->post($endpoint);
    $bodyActive = (string) $respActive->body();
    if (! preg_match('/<Ack>(Success|Warning)<\/Ack>/', $bodyActive)) {
        if (preg_match('/<LongMessage>([^<]+)<\/LongMessage>/', $bodyActive, $em)) {
            echo "Page {$page} error: {$em[1]}\n";
        }
        break;
    }
    if (preg_match_all('/<Item>.*?<\/Item>/s', $bodyActive, $blocks)) {
        foreach ($blocks[0] as $block) {
            preg_match('/<ItemID>(\d+)<\/ItemID>/', $block, $idM);
            preg_match('/<SKU>([^<]*)<\/SKU>/', $block, $skuM);
            $skus = [strtoupper(trim($skuM[1] ?? ''))];
            if (preg_match_all('/<Variation>.*?<SKU>([^<]*)<\/SKU>.*?<\/Variation>/s', $block, $varSkus)) {
                foreach ($varSkus[1] as $vs) {
                    $skus[] = strtoupper(trim($vs));
                }
            }
            if (in_array($targetUpper, $skus, true)) {
                preg_match('/<Title>([^<]*)<\/Title>/', $block, $titleM);
                $foundActive = ['item_id' => $idM[1] ?? '', 'sku' => $skuM[1] ?? '', 'title' => $titleM[1] ?? ''];
                break 2;
            }
            if (stripos($block, 'GFF') !== false && stripos($block, 'TP') !== false) {
                preg_match('/<Title>([^<]*)<\/Title>/', $block, $titleM);
                echo '  Near match item '.($idM[1] ?? '?').' sku='.($skuM[1] ?? '').' title='.substr($titleM[1] ?? '', 0, 70)."\n";
            }
        }
    }
    if ($page === 1 && preg_match('/<TotalNumberOfPages>(\d+)<\/TotalNumberOfPages>/', $bodyActive, $tp)) {
        echo 'ActiveList pages: '.$tp[1]."\n";
    }
    $page++;
}
if ($foundActive) {
    echo 'FOUND via GetMyeBaySelling: '.json_encode($foundActive)."\n";
} else {
    echo "Not found in GetMyeBaySelling ActiveList\n";
}
