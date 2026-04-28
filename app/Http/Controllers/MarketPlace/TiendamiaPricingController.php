<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\TiendamiaDataView;
use App\Models\TiendamiaPriceUpload;
use App\Models\TiendamiaProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

class TiendamiaPricingController extends Controller
{
    private static function normalizeTiendamiaSku(string $value): string
    {
        return strtoupper(str_replace("\u{00a0}", ' ', trim($value)));
    }

    /** Normalize export header cell for column matching (NBSP, trim, collapse spaces, lowercase). */
    private static function normalizeUploadHeaderKey(string $headerCell): string
    {
        $s = str_replace(["\xC2\xA0", "\xE2\x80\xAF", "\xE2\x80\x8B"], ' ', $headerCell);
        $s = trim($s);
        $s = preg_replace('/\s+/u', ' ', $s);

        return strtolower($s);
    }

    /**
     * Prefer TAB when the raw line has many tab characters (Mirakl / teinda.txt), so commas inside
     * the Product column do not force wrong CSV detection. Otherwise semicolon or comma CSV.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    private static function detectDelimiterAndHeader(string $firstLine): array
    {
        $line = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
        $line = rtrim($line, "\r\n");
        $line = self::scrubUtf8String($line);

        $tabSep = substr_count($line, "\t");
        $semiSep = substr_count($line, ';');

        if ($tabSep >= 5) {
            return ["\t", str_getcsv($line, "\t")];
        }
        if ($semiSep >= 5) {
            return [';', str_getcsv($line, ';')];
        }

        return [',', str_getcsv($line, ',')];
    }

    /**
     * Excel sometimes saves as UTF-16; convert to UTF-8 in a temp file so fgetcsv sees valid bytes.
     *
     * @return array{0: string, 1: bool} [path, whether path is a temp file to unlink]
     */
    private static function ensureUtf8UploadPath(string $path): array
    {
        $probe = @file_get_contents($path, false, null, 0, 4);
        if ($probe === false || $probe === '') {
            return [$path, false];
        }
        if (str_starts_with($probe, "\xFF\xFE")) {
            $raw = file_get_contents($path);
            if ($raw === false) {
                return [$path, false];
            }
            $utf8 = mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE');
            $tmp = tempnam(sys_get_temp_dir(), 'tmupl');
            if ($tmp === false) {
                return [$path, false];
            }
            file_put_contents($tmp, $utf8);

            return [$tmp, true];
        }
        if (str_starts_with($probe, "\xFE\xFF")) {
            $raw = file_get_contents($path);
            if ($raw === false) {
                return [$path, false];
            }
            $utf8 = mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE');
            $tmp = tempnam(sys_get_temp_dir(), 'tmupl');
            if ($tmp === false) {
                return [$path, false];
            }
            file_put_contents($tmp, $utf8);

            return [$tmp, true];
        }

        return [$path, false];
    }

    /** Remove invalid UTF-8 sequences and fix common Windows-1252 / Latin-1 mojibake for MySQL + JSON. */
    private static function scrubUtf8String(string $value): string
    {
        $v = $value;
        if ($v !== '' && ! mb_check_encoding($v, 'UTF-8')) {
            $try = @mb_convert_encoding($v, 'UTF-8', 'Windows-1252');
            if ($try !== false && mb_check_encoding($try, 'UTF-8')) {
                $v = $try;
            } else {
                $try = @mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1');
                if ($try !== false && mb_check_encoding($try, 'UTF-8')) {
                    $v = $try;
                }
            }
        }
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $v);

        return $clean !== false ? $clean : '';
    }

    private static function sanitizeUploadNullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $t = trim(self::scrubUtf8String((string) $value));

        return $t === '' ? null : $t;
    }

    /**
     * Listing / variant SKUs that should not appear on Tiendamia pricing (aligned with OPEN BOX handling elsewhere).
     */
    private static function isOpenBoxListingSku(string $sku): bool
    {
        $s = trim(str_replace("\u{00a0}", ' ', $sku));
        if ($s === '') {
            return false;
        }
        if (stripos($s, 'OPEN BOX') !== false) {
            return true;
        }
        $t = strtolower(preg_replace('/\s+/u', ' ', $s));

        return (bool) preg_match('/(open[ _-]+box|open-box|open_box|openbox)$/', $t);
    }

    /**
     * M Ship: manual ship by WT ACT band when weight is known and ≤ 20; otherwise use product_master ship.
     */
    private static function resolveManualShipFromWtAct(?float $wtAct, float $shipFromProductMaster): float
    {
        if ($wtAct === null || ! is_finite($wtAct)) {
            return $shipFromProductMaster;
        }
        $w = $wtAct;
        if ($w <= 0.25) {
            return 5.62;
        }
        if ($w <= 0.5) {
            return 5.97;
        }
        if ($w <= 0.75) {
            return 6.58;
        }
        if ($w <= 0.99) {
            return 7.6;
        }
        if ($w <= 20) {
            return 8.75;
        }

        return $shipFromProductMaster;
    }

    private static function spreadsheetCellToPlainString(mixed $cell): string
    {
        if ($cell === null) {
            return '';
        }
        if ($cell instanceof RichText) {
            return self::scrubUtf8String($cell->getPlainText());
        }
        if (is_float($cell) || is_int($cell)) {
            return self::scrubUtf8String(trim((string) $cell));
        }

        return self::scrubUtf8String(trim((string) $cell));
    }

    /** Excel .xlsx/.xlsm (ZIP + [Content_Types].xml), .xls, .ods, or extension hint. */
    private static function looksLikeSpreadsheetPriceUpload(string $path, string $ext): bool
    {
        $e = strtolower($ext);
        if (in_array($e, ['xlsx', 'xlsm', 'xls', 'ods'], true)) {
            return true;
        }
        $probe = @file_get_contents($path, false, null, 0, 4096);
        if ($probe === false || strlen($probe) < 4) {
            return false;
        }
        if (str_starts_with($probe, 'PK') && str_contains($probe, '[Content_Types].xml')) {
            return true;
        }

        return false;
    }

    /**
     * @return array{error: ?string, header: array<int, string>, data_rows: array<int, array<int, string>>}
     */
    private static function loadTiendamiaSpreadsheetMatrix(string $path): array
    {
        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            return [
                'error' => 'Could not open this spreadsheet. Use .xlsx / .xls from Excel, or export plain UTF-8 CSV/TSV from Mirakl. Details: '.self::scrubUtf8String($e->getMessage()),
                'header' => [],
                'data_rows' => [],
            ];
        }

        $sheet = $spreadsheet->getActiveSheet();
        $raw = $sheet->toArray(null, true, true, false);
        if ($raw === [] || $raw === [[]]) {
            return ['error' => 'The first worksheet is empty.', 'header' => [], 'data_rows' => []];
        }

        $headerRow = array_shift($raw);
        $header = [];
        foreach ($headerRow as $cell) {
            $header[] = self::spreadsheetCellToPlainString($cell);
        }

        $dataRows = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cells = [];
            foreach ($row as $cell) {
                $cells[] = self::spreadsheetCellToPlainString($cell);
            }
            $dataRows[] = array_values($cells);
        }

        return ['error' => null, 'header' => $header, 'data_rows' => $dataRows];
    }

    /**
     * @return array{col_index: array<string, int>, error: ?string}
     */
    private static function buildTiendamiaPriceUploadColumnIndex(array $header, string $formatLabel): array
    {
        $colIndex = [];
        foreach ($header as $i => $name) {
            $key = self::normalizeUploadHeaderKey($name);
            if ($key !== '') {
                $colIndex[$key] = $i;
            }
        }

        if (! isset($colIndex['offer sku'])) {
            foreach ($header as $i => $name) {
                $k = self::normalizeUploadHeaderKey($name);
                if ($k === 'offersku' || $k === 'offer-sku' || $k === 'offer_sku' || preg_match('/^offer\s+sku$/u', $k)) {
                    $colIndex['offer sku'] = $i;
                    break;
                }
            }
        }
        if (! isset($colIndex['price'])) {
            foreach ($header as $i => $name) {
                $k = self::normalizeUploadHeaderKey($name);
                if ($k === 'current price' || $k === 'selling price' || $k === 'your price') {
                    $colIndex['price'] = $i;
                    break;
                }
            }
        }

        foreach (['offer sku', 'price'] as $rk) {
            if (! isset($colIndex[$rk])) {
                $preview = self::scrubUtf8String(implode(' | ', array_slice($header, 0, min(12, count($header)))));

                return [
                    'col_index' => [],
                    'error' => 'Missing required column "'.$rk.'". First row must include Offer SKU and Price (same columns as teinda.txt). Format detected: '.$formatLabel.'. Header preview: '.$preview,
                ];
            }
        }

        return ['col_index' => $colIndex, 'error' => null];
    }

    /**
     * @param  array<int, array<int, string>>  $dataRows
     * @return array{rows_to_insert: array<int, array<string, mixed>>, parsed_for_merge: array<int, array<string, mixed>>, skipped_empty_offer_sku: int}
     */
    private static function materializeTiendamiaPriceUploadRows(
        array $colIndex,
        array $dataRows,
        string $batchId,
        string $sourceName,
        string $now
    ): array {
        $pick = static function (array $cols, array $colIndex, string $logicalName): ?string {
            $i = $colIndex[self::normalizeUploadHeaderKey($logicalName)] ?? null;
            if ($i === null || ! isset($cols[$i])) {
                return null;
            }

            return self::sanitizeUploadNullable((string) $cols[$i]);
        };

        $parseDecimal = static function (?string $v): ?float {
            $s = self::sanitizeUploadNullable($v);
            if ($s === null) {
                return null;
            }
            $s = str_replace([',', ' '], ['', ''], $s);
            if (! is_numeric($s)) {
                return null;
            }

            return (float) $s;
        };

        $rowsToInsert = [];
        $parsedForMerge = [];
        $dataRowIndex = 0;
        $skippedEmptySku = 0;

        foreach ($dataRows as $cols) {
            if ($cols === []) {
                continue;
            }
            if (count($cols) === 1 && trim((string) $cols[0]) === '') {
                continue;
            }
            $cols = array_map(static fn ($c) => self::scrubUtf8String((string) $c), $cols);
            $dataRowIndex++;
            $offerSku = $pick($cols, $colIndex, 'Offer SKU');
            if ($offerSku === null || $offerSku === '') {
                $skippedEmptySku++;

                continue;
            }

            $priceVal = $parseDecimal($pick($cols, $colIndex, 'Price'));
            $origVal = $parseDecimal($pick($cols, $colIndex, 'Original price'));
            $discVal = $parseDecimal($pick($cols, $colIndex, 'Discount price'));
            $qtyStr = $pick($cols, $colIndex, 'Quantity');
            $qty = ($qtyStr !== null && is_numeric($qtyStr)) ? (int) $qtyStr : null;

            $rowsToInsert[] = [
                'upload_batch_id' => $batchId,
                'source_filename' => $sourceName,
                'row_index' => $dataRowIndex,
                'offer_sku' => $offerSku,
                'product_sku' => $pick($cols, $colIndex, 'Product SKU'),
                'category_code' => $pick($cols, $colIndex, 'Category code'),
                'category_label' => $pick($cols, $colIndex, 'Category label'),
                'brand' => $pick($cols, $colIndex, 'Brand'),
                'product' => $pick($cols, $colIndex, 'Product'),
                'offer_state' => $pick($cols, $colIndex, 'Offer state'),
                'price' => $priceVal,
                'original_price' => $origVal,
                'quantity' => $qty,
                'alert_threshold' => $pick($cols, $colIndex, 'Alert threshold'),
                'logistic_class' => $pick($cols, $colIndex, 'Logistic Class'),
                'activated' => $pick($cols, $colIndex, 'Activated'),
                'available_start_date' => $pick($cols, $colIndex, 'Available Start Date'),
                'available_end_date' => $pick($cols, $colIndex, 'Available End Date'),
                'discount_price' => $discVal,
                'discount_start_date' => $pick($cols, $colIndex, 'Discount Start Date'),
                'discount_end_date' => $pick($cols, $colIndex, 'Discount End Date'),
                'ean' => $pick($cols, $colIndex, 'EAN'),
                'inactivity_reason' => $pick($cols, $colIndex, 'Inactivity reason'),
                'fulfillment_center_code' => $pick($cols, $colIndex, 'Fulfillment center code'),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($priceVal !== null) {
                $parsedForMerge[] = [
                    'offer_sku' => $offerSku,
                    'price' => $priceVal,
                    'original_price' => $origVal,
                    'discount_price' => $discVal,
                ];
            }
        }

        return [
            'rows_to_insert' => $rowsToInsert,
            'parsed_for_merge' => $parsedForMerge,
            'skipped_empty_offer_sku' => $skippedEmptySku,
        ];
    }

    public function tabulatorView()
    {
        return view('market-places.tiendamia_tabulator_view');
    }

    /**
     * Tabulator JSON: tiendamia_products joined to product_master + shopify_skus (image, INV, OV L30).
     * SKU normalization matches AliExpress pricing (NBSP → space, trim, uppercase).
     */
    public function getTabulatorData()
    {
        try {
            $normalizeSku = static fn ($value) => self::normalizeTiendamiaSku((string) $value);

            $productMastersBySku = ProductMaster::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->whereRaw('UPPER(sku) NOT LIKE ?', ['%PARENT%'])
                ->get()
                ->keyBy(fn ($row) => $normalizeSku($row->sku));

            $tiendamiaProducts = TiendamiaProduct::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->get()
                ->filter(function ($tp) {
                    $sku = trim((string) ($tp->sku ?? ''));

                    return $sku !== '' && ! self::isOpenBoxListingSku($sku);
                })
                ->values();

            $rawSkus = $tiendamiaProducts
                ->pluck('sku')
                ->map(fn ($s) => trim((string) $s))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $shopifyByNorm = $rawSkus === []
                ? []
                : ShopifySku::buildShopifySkuLookupByNormalizedSku($rawSkus);

            $marketplaceData = MarketplacePercentage::query()
                ->where('marketplace', 'Tiendamia')
                ->first();
            $percentage = $marketplaceData ? ((float) ($marketplaceData->percentage ?? 100)) : 100;
            $margin = $percentage / 100;

            $dataViewsByNorm = [];
            if ($rawSkus !== []) {
                foreach (TiendamiaDataView::query()->whereIn('sku', $rawSkus)->get() as $dv) {
                    $dataViewsByNorm[$normalizeSku($dv->sku)] = $dv;
                }
            }

            $rows = [];
            foreach ($tiendamiaProducts as $tp) {
                $sku = trim((string) ($tp->sku ?? ''));
                if ($sku === '') {
                    continue;
                }

                $normalizedSku = $normalizeSku($sku);
                $productMaster = $productMastersBySku->get($normalizedSku);
                $shopifyRow = $shopifyByNorm[$normalizedSku] ?? null;

                $inv = $shopifyRow ? (int) ($shopifyRow->inv ?? 0) : 0;
                $ovL30 = $shopifyRow ? (int) ($shopifyRow->quantity ?? 0) : 0;
                $imageSrc = $shopifyRow ? ($shopifyRow->image_src ?? null) : null;

                if (! $imageSrc && $productMaster) {
                    $values = is_array($productMaster->Values)
                        ? $productMaster->Values
                        : (is_string($productMaster->Values) ? json_decode($productMaster->Values, true) : []);
                    $imageSrc = $values['image_path'] ?? $productMaster->image_path ?? null;
                }

                $parent = $productMaster ? trim((string) ($productMaster->parent ?? '')) : '';
                $tmStock = (int) ($tp->stock ?? 0);
                $price = (float) ($tp->price ?? 0);
                $isMissing = ! $productMaster || $price <= 0;

                if ($isMissing) {
                    $mapValue = '';
                } else {
                    $diff = abs($inv - $tmStock);
                    $mapValue = $diff <= 3 ? 'Map' : 'N Map|' . $diff;
                }

                $dilPercent = $inv > 0 ? round(($ovL30 / $inv) * 100, 2) : 0;

                $lp = 0;
                $ship = 0;
                $wtAct = null;
                if ($productMaster) {
                    $values = is_array($productMaster->Values)
                        ? $productMaster->Values
                        : (is_string($productMaster->Values) ? json_decode($productMaster->Values, true) : []);
                    if (! is_array($values)) {
                        $values = [];
                    }
                    $lp = isset($values['lp']) ? (float) $values['lp'] : (isset($productMaster->lp) ? (float) $productMaster->lp : 0);
                    $ship = isset($values['ship']) ? (float) $values['ship'] : (isset($productMaster->ship) ? (float) $productMaster->ship : 0);
                    if (array_key_exists('wt_act', $values) && $values['wt_act'] !== null && $values['wt_act'] !== '' && is_numeric($values['wt_act'])) {
                        $wtAct = (float) $values['wt_act'];
                    } elseif (isset($productMaster->wt_act) && $productMaster->wt_act !== null && $productMaster->wt_act !== '' && is_numeric($productMaster->wt_act)) {
                        $wtAct = (float) $productMaster->wt_act;
                    }
                }

                $mL30 = (int) ($tp->m_l30 ?? 0);
                $mShip = self::resolveManualShipFromWtAct($wtAct, $ship);
                $profitUnit = ($price * $margin) - $lp - $mShip;
                $gpft = $price > 0 ? ($profitUnit / $price) * 100 : 0;
                $groi = $lp > 0 ? ($profitUnit / $lp) * 100 : 0;
                $sales = $price * $mL30;

                $viewRow = $dataViewsByNorm[$normalizedSku] ?? null;
                $meta = ($viewRow && is_array($viewRow->value)) ? $viewRow->value : [];
                $sprice = isset($meta['SPRICE']) ? (float) $meta['SPRICE'] : 0;
                $sgpft = $sprice > 0 ? (int) round((($sprice * $margin - $lp - $mShip) / $sprice) * 100) : 0;
                $sroi = $lp > 0 ? (int) round((($sprice * $margin - $lp - $mShip) / $lp) * 100) : 0;

                $rows[] = [
                    'id' => (int) $tp->id,
                    'sku' => $sku,
                    'parent' => $parent !== '' ? $parent : null,
                    'image' => $imageSrc,
                    'inv' => $inv,
                    'tm_stock' => $tmStock,
                    'ov_l30' => $ovL30,
                    'dil_percent' => $dilPercent,
                    'm_l30' => $mL30,
                    'm_l60' => (int) ($tp->m_l60 ?? 0),
                    'al30' => $mL30,
                    'price' => round($price, 2),
                    'lp' => round($lp, 2),
                    'ship' => round($ship, 2),
                    'wt_act' => $wtAct !== null ? round($wtAct, 2) : null,
                    'm_ship' => round($mShip, 2),
                    'profit' => round($profitUnit, 2),
                    'sales' => round($sales, 2),
                    'sprice' => round($sprice, 2),
                    'sgpft' => $sgpft,
                    'sroi' => $sroi,
                    'gpft' => (int) round($gpft),
                    'groi' => (int) round($groi),
                    'map' => $mapValue,
                    'lmp' => null,
                    'missing' => $isMissing ? 'M' : '',
                    'is_parent' => false,
                    '_margin' => round($margin, 4),
                ];
            }

            usort($rows, static fn ($a, $b) => strnatcasecmp($a['sku'], $b['sku']));

            return response()->json($rows, 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Throwable $e) {
            Log::error('Tiendamia tabulator data failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch Tiendamia products: ' . $e->getMessage(),
            ], 500, [], JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }

    /**
     * Save SPRICE + SGPFT + SROI to tiendamia_data_views.value (same keys as AliExpress pricing).
     */
    public function saveSpriceUpdates(Request $request)
    {
        try {
            $updates = $request->input('updates', []);
            if ($updates === [] && $request->has('sku')) {
                $updates = [['sku' => $request->input('sku'), 'sprice' => $request->input('sprice')]];
            }

            $marketplaceData = MarketplacePercentage::query()
                ->where('marketplace', 'Tiendamia')
                ->first();
            $percentage = $marketplaceData ? ((float) ($marketplaceData->percentage ?? 100)) : 100;
            $margin = $percentage / 100;

            $productMastersBySku = ProductMaster::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->get()
                ->keyBy(fn ($row) => self::normalizeTiendamiaSku((string) $row->sku));

            $updatedCount = 0;
            foreach ($updates as $update) {
                $sku = isset($update['sku']) ? trim((string) $update['sku']) : '';
                $sprice = $update['sprice'] ?? null;
                if ($sku === '' || $sprice === null || self::isOpenBoxListingSku($sku)) {
                    continue;
                }

                $sprice = (float) $sprice;
                $productMaster = $productMastersBySku->get(self::normalizeTiendamiaSku($sku));

                $lp = 0;
                $ship = 0;
                $wtAct = null;
                if ($productMaster) {
                    $values = is_array($productMaster->Values)
                        ? $productMaster->Values
                        : (is_string($productMaster->Values) ? json_decode($productMaster->Values, true) : []);
                    if (! is_array($values)) {
                        $values = [];
                    }
                    $lp = isset($values['lp']) ? (float) $values['lp'] : (isset($productMaster->lp) ? (float) $productMaster->lp : 0);
                    $ship = isset($values['ship']) ? (float) $values['ship'] : (isset($productMaster->ship) ? (float) $productMaster->ship : 0);
                    if (array_key_exists('wt_act', $values) && $values['wt_act'] !== null && $values['wt_act'] !== '' && is_numeric($values['wt_act'])) {
                        $wtAct = (float) $values['wt_act'];
                    } elseif (isset($productMaster->wt_act) && $productMaster->wt_act !== null && $productMaster->wt_act !== '' && is_numeric($productMaster->wt_act)) {
                        $wtAct = (float) $productMaster->wt_act;
                    }
                }
                $mShip = self::resolveManualShipFromWtAct($wtAct, $ship);

                $sgpft = $sprice > 0 ? (int) round((($sprice * $margin - $lp - $mShip) / $sprice) * 100) : 0;
                $sroi = $lp > 0 ? (int) round((($sprice * $margin - $lp - $mShip) / $lp) * 100) : 0;

                $view = TiendamiaDataView::firstOrNew(['sku' => $sku]);
                $stored = is_array($view->value) ? $view->value : (json_decode($view->value, true) ?: []);

                $stored['SPRICE'] = $sprice;
                $stored['SGPFT'] = $sgpft;
                $stored['SPFT'] = $sgpft;
                $stored['SROI'] = $sroi;

                $view->value = $stored;
                $view->save();
                $updatedCount++;
            }

            return response()->json(['success' => true, 'updated' => $updatedCount]);
        } catch (\Throwable $e) {
            Log::error('Tiendamia SPRICE save failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Clears tiendamia_price_uploads, stores Mirakl-style rows (teinda.txt / CSV / TSV / .xlsx first sheet),
     * then updates tiendamia_products (price / standard_price / discount_price when columns exist) by Offer SKU.
     */
    public function uploadPriceFile(Request $request)
    {
        $request->validate([
            'price_file' => 'required|file|max:102400',
        ]);

        $file = $request->file('price_file');
        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            return response()->json(['success' => false, 'error' => 'Could not read uploaded file.'], 422, [], JSON_INVALID_UTF8_SUBSTITUTE);
        }

        $ext = strtolower((string) $file->getClientOriginalExtension());
        $batchId = (string) Str::uuid();
        $sourceName = self::sanitizeUploadNullable($file->getClientOriginalName()) ?? 'upload';
        $now = now()->toDateTimeString();
        $rowsToInsert = [];
        $parsedForMerge = [];
        $skippedEmptySku = 0;

        if (self::looksLikeSpreadsheetPriceUpload($path, $ext)) {
            $loaded = self::loadTiendamiaSpreadsheetMatrix($path);
            if ($loaded['error'] !== null) {
                return response()->json(['success' => false, 'error' => $loaded['error']], 422, [], JSON_INVALID_UTF8_SUBSTITUTE);
            }
            $header = array_map(static fn ($h) => self::scrubUtf8String(trim((string) $h)), $loaded['header']);
            $idx = self::buildTiendamiaPriceUploadColumnIndex($header, 'EXCEL / ODS (active sheet)');
            if ($idx['error'] !== null) {
                return response()->json(['success' => false, 'error' => $idx['error']], 422, [], JSON_INVALID_UTF8_SUBSTITUTE);
            }
            $mat = self::materializeTiendamiaPriceUploadRows($idx['col_index'], $loaded['data_rows'], $batchId, $sourceName, $now);
            $rowsToInsert = $mat['rows_to_insert'];
            $parsedForMerge = $mat['parsed_for_merge'];
            $skippedEmptySku = $mat['skipped_empty_offer_sku'];
        } else {
            [$workPath, $useTempFile] = self::ensureUtf8UploadPath($path);

            $handle = @fopen($workPath, 'r');
            if ($handle === false) {
                if ($useTempFile) {
                    @unlink($workPath);
                }

                return response()->json(['success' => false, 'error' => 'Could not open uploaded file.'], 422, [], JSON_INVALID_UTF8_SUBSTITUTE);
            }

            try {
                $firstLine = fgets($handle);
                if ($firstLine === false || trim($firstLine) === '') {
                    return response()->json(['success' => false, 'error' => 'File is empty or missing header row.'], 422, [], JSON_INVALID_UTF8_SUBSTITUTE);
                }

                [$delimiter, $header] = self::detectDelimiterAndHeader($firstLine);
                if ($header === [] || $header === [null]) {
                    return response()->json(['success' => false, 'error' => 'Could not parse header row.'], 422, [], JSON_INVALID_UTF8_SUBSTITUTE);
                }

                $header = array_map(static fn ($h) => self::scrubUtf8String(trim((string) $h)), $header);
                $formatLabel = $delimiter === "\t"
                    ? 'TAB'
                    : ($delimiter === ',' ? 'COMMA' : 'SEMICOLON');
                $idx = self::buildTiendamiaPriceUploadColumnIndex(
                    $header,
                    $formatLabel.' (tab, comma, or semicolon — same columns as teinda.txt)'
                );
                if ($idx['error'] !== null) {
                    return response()->json(['success' => false, 'error' => $idx['error']], 422, [], JSON_INVALID_UTF8_SUBSTITUTE);
                }

                $dataRows = [];
                while (($cols = fgetcsv($handle, 0, $delimiter)) !== false) {
                    if ($cols === [null]) {
                        continue;
                    }
                    if (count($cols) === 1 && trim((string) $cols[0]) === '') {
                        continue;
                    }
                    $dataRows[] = array_map(static fn ($c) => self::scrubUtf8String((string) $c), $cols);
                }

                $mat = self::materializeTiendamiaPriceUploadRows($idx['col_index'], $dataRows, $batchId, $sourceName, $now);
                $rowsToInsert = $mat['rows_to_insert'];
                $parsedForMerge = $mat['parsed_for_merge'];
                $skippedEmptySku = $mat['skipped_empty_offer_sku'];
            } finally {
                if (is_resource($handle)) {
                    fclose($handle);
                }
                if ($useTempFile) {
                    @unlink($workPath);
                }
            }
        }

        if ($rowsToInsert === []) {
            return response()->json(['success' => false, 'error' => 'No data rows found after the header.'], 422, [], JSON_INVALID_UTF8_SUBSTITUTE);
        }

        $productsUpdated = 0;
        $skippedOpenBox = 0;
        $skippedNoProduct = 0;

        try {
            DB::beginTransaction();

            TiendamiaPriceUpload::query()->delete();

            foreach (array_chunk($rowsToInsert, 250) as $chunk) {
                TiendamiaPriceUpload::query()->insert($chunk);
            }

            if ($parsedForMerge !== []) {
                $byNorm = [];
                foreach (TiendamiaProduct::query()->cursor() as $tp) {
                    $byNorm[self::normalizeTiendamiaSku((string) $tp->sku)] = $tp;
                }

                $hasStandard = Schema::hasColumn('tiendamia_products', 'standard_price');
                $hasDiscountCol = Schema::hasColumn('tiendamia_products', 'discount_price');

                foreach ($parsedForMerge as $m) {
                    if (self::isOpenBoxListingSku($m['offer_sku'])) {
                        $skippedOpenBox++;

                        continue;
                    }
                    $norm = self::normalizeTiendamiaSku($m['offer_sku']);
                    $tp = $byNorm[$norm] ?? null;
                    if (! $tp) {
                        $skippedNoProduct++;

                        continue;
                    }

                    $update = ['price' => $m['price']];
                    if ($hasStandard) {
                        $update['standard_price'] = $m['original_price'];
                    }
                    if ($hasDiscountCol) {
                        $update['discount_price'] = $m['discount_price'];
                    }

                    TiendamiaProduct::query()->whereKey($tp->id)->update($update);
                    $productsUpdated++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Tiendamia price upload failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'error' => self::scrubUtf8String($e->getMessage()),
            ], 500, [], JSON_INVALID_UTF8_SUBSTITUTE);
        }

        return response()->json([
            'success' => true,
            'upload_batch_id' => $batchId,
            'rows_stored' => count($rowsToInsert),
            'products_updated' => $productsUpdated,
            'skipped_no_matching_sku' => $skippedNoProduct,
            'skipped_open_box' => $skippedOpenBox,
            'skipped_empty_offer_sku' => $skippedEmptySku,
            'upload_table_cleared' => true,
        ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
