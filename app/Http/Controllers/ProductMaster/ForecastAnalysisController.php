<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\AmazonDataView;
use App\Models\JungleScoutProductData;
use App\Models\Supplier;
use App\Models\TransitContainerDetail;
use App\Models\MfrgProgress;
use App\Models\ReadyToShip;

class ForecastAnalysisController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    private function buildForecastAnalysisData()
    {
        // Improved normalization to handle multiple spaces and hidden whitespace
        $normalizeSku = function ($sku) {
            if (empty($sku)) return '';
            $sku = strtoupper(trim($sku));
            $sku = preg_replace('/\s+/u', ' ', $sku);         // collapse multiple spaces to single space
            $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);  // remove hidden whitespace characters
            return trim($sku);
        };
        // Canonical SKU form for robust matching across sources (handles spaces, dashes, underscores, etc.)
        $canonicalSku = function ($sku) use ($normalizeSku) {
            $n = $normalizeSku($sku);
            if ($n === '') return '';
            return preg_replace('/[^A-Z0-9]/', '', $n);
        };

        $jungleScoutData = JungleScoutProductData::query()
            ->get()
            ->groupBy(fn($item) => $normalizeSku($item->parent))
            ->map(function ($group) {
                $validPrices = $group->filter(function ($item) {
                    $data = is_array($item->data) ? $item->data : [];
                    $price = $data['price'] ?? null;
                    return is_numeric($price) && $price > 0;
                })->pluck('data.price');

                return [
                    'scout_parent' => $group->first()->parent,
                    'min_price' => $validPrices->isNotEmpty() ? $validPrices->min() : null,
                    'product_count' => $group->count(),
                    'all_data' => $group->map(function ($item) {
                        $data = is_array($item->data) ? $item->data : [];
                        if (isset($data['price'])) {
                            $data['price'] = is_numeric($data['price']) ? (float) $data['price'] : null;
                        }
                        return $data;
                    })->toArray()
                ];
            });

        $productListData = DB::table('product_master')->whereNull('deleted_at')->get();

        // Load all shopify data and normalize SKUs for matching
        $shopifyData = ShopifySku::all()->keyBy(function($item) use ($normalizeSku) {
            return $normalizeSku($item->sku);
        });

        $amazonPriceBySku = [];
        foreach (DB::table('amazon_datsheets')->whereNotNull('sku')->get(['sku', 'price']) as $ar) {
            $k = $normalizeSku($ar->sku ?? '');
            if ($k === '') {
                continue;
            }
            $p = is_numeric($ar->price) ? (float) $ar->price : 0.0;
            if ($p > 0) {
                $amazonPriceBySku[$k] = $p;
            }
        }
        $resolveAmazonPrice = function ($sheetSku) use ($amazonPriceBySku, $normalizeSku) {
            $k = $normalizeSku($sheetSku);
            if (isset($amazonPriceBySku[$k]) && $amazonPriceBySku[$k] > 0) {
                return $amazonPriceBySku[$k];
            }
            $skuNoSpaces = str_replace(' ', '', $k);
            foreach ($amazonPriceBySku as $key => $p) {
                if ($p > 0 && str_replace(' ', '', $key) === $skuNoSpaces) {
                    return $p;
                }
            }

            return 0.0;
        };

        $amazonAdvBySku = [];
        foreach (AmazonDataView::query()->get(['sku', 'value']) as $advRow) {
            $k = $normalizeSku($advRow->sku ?? '');
            if ($k === '') {
                continue;
            }
            $v = $advRow->value;
            if (! is_array($v)) {
                $v = json_decode($advRow->value ?? '{}', true) ?: [];
            }
            $amazonAdvBySku[$k] = $v;
        }
        $getAmazonAdv = function ($sheetSku) use ($amazonAdvBySku, $normalizeSku) {
            $k = $normalizeSku($sheetSku);
            if (isset($amazonAdvBySku[$k])) {
                return $amazonAdvBySku[$k];
            }
            $skuNoSpaces = str_replace(' ', '', $k);
            foreach ($amazonAdvBySku as $key => $val) {
                if (str_replace(' ', '', $key) === $skuNoSpaces) {
                    return $val;
                }
            }

            return null;
        };

        $jungleReviewsBySku = [];
        $jungleRatingBySku = [];
        foreach (JungleScoutProductData::query()->get(['sku', 'data']) as $jsRow) {
            $k = $normalizeSku($jsRow->sku ?? '');
            if ($k === '') {
                continue;
            }
            $d = is_array($jsRow->data) ? $jsRow->data : [];
            $rv = $d['reviews'] ?? null;
            if ($rv !== null && $rv !== '') {
                $jungleReviewsBySku[$k] = is_numeric($rv) ? (int) $rv : (string) $rv;
            }
            $rat = $d['rating'] ?? null;
            if ($rat !== null && $rat !== '') {
                $jungleRatingBySku[$k] = is_numeric($rat) ? round((float) $rat, 2) : (string) $rat;
            }
        }
        $getJungleReviews = function ($sheetSku) use ($jungleReviewsBySku, $normalizeSku) {
            $k = $normalizeSku($sheetSku);
            if (isset($jungleReviewsBySku[$k])) {
                return $jungleReviewsBySku[$k];
            }
            $skuNoSpaces = str_replace(' ', '', $k);
            foreach ($jungleReviewsBySku as $key => $rv) {
                if (str_replace(' ', '', $key) === $skuNoSpaces) {
                    return $rv;
                }
            }

            return null;
        };
        $getJungleRating = function ($sheetSku) use ($jungleRatingBySku, $normalizeSku) {
            $k = $normalizeSku($sheetSku);
            if (isset($jungleRatingBySku[$k])) {
                return $jungleRatingBySku[$k];
            }
            $skuNoSpaces = str_replace(' ', '', $k);
            foreach ($jungleRatingBySku as $key => $rv) {
                if (str_replace(' ', '', $key) === $skuNoSpaces) {
                    return $rv;
                }
            }

            return null;
        };

        $supplierRows = Supplier::where('type', 'Supplier')->get();
        $supplierMapByParent = [];
        foreach ($supplierRows as $row) {
            $parents = array_map('trim', explode(',', strtoupper($row->parent ?? '')));
            foreach ($parents as $parent) {
                if (!empty($parent)) {
                    $supplierMapByParent[$parent][] = $row->name;
                }
            }
        }

        // Key forecastMap by SKU only for easier matching
        // If multiple records exist for same SKU, prefer the one with non-empty stage value, then non-empty nr value
        $forecastMap = DB::table('forecast_analysis')->get()->groupBy(function($item) use ($normalizeSku) {
            return $normalizeSku($item->sku);
        })->map(function($group) {
            // Prefer record with non-empty stage value
            $withStage = $group->firstWhere('stage', '!=', null);
            if ($withStage && !empty(trim($withStage->stage))) {
                return $withStage;
            }
            // Otherwise prefer one with non-empty nr
            $withNr = $group->firstWhere('nr', '!=', null);
            if ($withNr && !empty(trim($withNr->nr))) {
                return $withNr;
            }
            return $group->first();
        });
        $movementMap = DB::table('movement_analysis')->get()->keyBy(fn($item) => $normalizeSku($item->sku));
        $readyToShipRows = DB::table('ready_to_ship')
            ->where('transit_inv_status', 0)
            ->whereNull('deleted_at')
            ->get();
        $mergeReadyToShipRows = function($rows) {
            $preferred = $rows->first(function($r) {
                return trim((string)($r->pay_term ?? '')) !== '';
            }) ?? $rows->first();
            $pick = function($field) use ($rows, $preferred) {
                foreach ($rows as $r) {
                    $v = $r->{$field} ?? null;
                    if ($v !== null && trim((string)$v) !== '') return $v;
                }
                return $preferred->{$field} ?? null;
            };
            return (object)[
                'sku' => $preferred->sku ?? '',
                'qty' => (float)($pick('qty') ?? 0),
                'rec_qty' => $pick('rec_qty'),
                'rate' => (float)($pick('rate') ?? 0),
                'payment' => $pick('payment') ?? 'No',
                'pay_term' => $pick('pay_term') ?? '',
                'packing_list' => $pick('packing_list') ?? 'No',
                'photo_mail_send' => $pick('photo_mail_send') ?? 'No',
                'area' => $pick('area') ?? '',
            ];
        };
        $readyToShipMap = $readyToShipRows
            ->groupBy(fn($item) => $normalizeSku($item->sku))
            ->map($mergeReadyToShipRows);
        $readyToShipMapCanonical = $readyToShipRows
            ->groupBy(fn($item) => $canonicalSku($item->sku))
            ->map($mergeReadyToShipRows);
        $getReadyToShipForSku = function($sku) use ($readyToShipMap, $readyToShipMapCanonical, $normalizeSku, $canonicalSku) {
            $k = $normalizeSku($sku);
            if ($k !== '' && $readyToShipMap->has($k)) return $readyToShipMap->get($k);
            $c = $canonicalSku($sku);
            if ($c !== '' && $readyToShipMapCanonical->has($c)) return $readyToShipMapCanonical->get($c);
            return null;
        };
        $toOrderRows = DB::table('to_order_analysis')
            ->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['id', 'sku', 'parent', 'approved_qty', 'updated_at']);
        $toOrderApprovedBySkuParent = collect($toOrderRows)
            ->groupBy(function ($r) use ($normalizeSku) {
                return $normalizeSku($r->sku) . '|' . $normalizeSku($r->parent ?? '');
            })
            ->map(function ($rows) {
                $latest = $rows->first();
                return (float) ($latest->approved_qty ?? 0);
            });
        $toOrderApprovedBySku = collect($toOrderRows)
            ->groupBy(fn ($r) => $normalizeSku($r->sku))
            ->map(function ($rows) {
                $latest = $rows->first();
                return (float) ($latest->approved_qty ?? 0);
            });
        $toOrderMetaBySku = collect(DB::table('to_order_analysis')
            ->whereNull('deleted_at')
            ->get(['sku', 'rfq_form_link', 'rfq_report_link', 'sheet_link']))
            ->groupBy(fn($r) => $normalizeSku($r->sku))
            ->map(function($rows) {
                $latest = $rows->sortByDesc('id')->first();
                $pick = function($field) use ($rows, $latest) {
                    foreach ($rows as $r) {
                        $v = trim((string)($r->{$field} ?? ''));
                        if ($v !== '') return $v;
                    }
                    return trim((string)($latest->{$field} ?? ''));
                };
                return (object)[
                    'rfq_form_link' => $pick('rfq_form_link'),
                    'rfq_report_link' => $pick('rfq_report_link'),
                    'sheet_link' => $pick('sheet_link'),
                ];
            });
        $mfrg = DB::table('mfrg_progress')->get()->keyBy(fn($item) => $normalizeSku($item->sku));
        $purchases = DB::table('purchases')->whereNull('deleted_at')
            ->select('items')
            ->get()
            ->flatMap(function ($row) {
                $items = json_decode($row->items);

                if (!is_array($items)) return [];
                
                return collect($items)->mapWithKeys(function ($item) {
                    if (!isset($item->sku)) return [];
                    return [$item->sku => $item];
                });
            });

        // Normalize container labels to stable codes (e.g. "Container 86", "C-86" -> "C-86")
        $normalizeContainerCode = function ($name) {
            $raw = strtoupper(trim((string)($name ?? '')));
            if ($raw === '') return '';
            if (preg_match('/(\d+)/', $raw, $m)) {
                return 'C-' . $m[1];
            }
            $raw = preg_replace('/\s+/u', ' ', $raw);
            return trim($raw);
        };

        // Get container records that have been successfully pushed to warehouse
        $warehousePushedRecords = DB::table('inventory_warehouse')
            ->where('push_status', 'success')
            ->select('our_sku', 'tab_name')
            ->get()
            ->map(function($record) use ($normalizeSku, $normalizeContainerCode, $canonicalSku) {
                return [
                    'sku' => $normalizeSku($record->our_sku ?? ''),
                    'sku_canonical' => $canonicalSku($record->our_sku ?? ''),
                    'container' => $normalizeContainerCode($record->tab_name ?? ''),
                ];
            })
            ->toArray();

        $transitContainer = TransitContainerDetail::whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('status')
                ->orWhere('status', '');
            })
            ->select('our_sku', 'tab_name', 'no_of_units', 'total_ctn', 'rate')
            ->get()
            ->filter(function ($item) use ($warehousePushedRecords, $normalizeSku, $normalizeContainerCode, $canonicalSku) {
                // Only exclude specific container records that have been pushed to warehouse
                $normalizedSku = $normalizeSku($item->our_sku ?? '');
                $canonicalItemSku = $canonicalSku($item->our_sku ?? '');
                $containerCode = $normalizeContainerCode($item->tab_name ?? '');
                
                // Check if this specific SKU + Container combination has been pushed to warehouse
                foreach ($warehousePushedRecords as $pushedRecord) {
                    $skuMatches = (
                        ($pushedRecord['sku'] !== '' && $pushedRecord['sku'] === $normalizedSku) ||
                        ($pushedRecord['sku_canonical'] !== '' && $pushedRecord['sku_canonical'] === $canonicalItemSku)
                    );
                    if ($skuMatches &&
                        $pushedRecord['container'] !== '' &&
                        $containerCode !== '' &&
                        $pushedRecord['container'] === $containerCode) {
                        return false; // Exclude this specific container record
                    }
                }
                return true; // Keep this record
            })
            ->groupBy(fn($item) => $normalizeSku($item->our_sku ?? ''))
            ->map(function ($group) {
                $transitSum = 0;
                $transitValueSum = 0; // Sum of (qty * rate) for each row (like transit-container-details page)
                $rate = 0;
                foreach ($group as $row) {
                    $no_of_units = (float) ($row->no_of_units ?? 0);
                    $total_ctn = (float) ($row->total_ctn ?? 0);
                    $rowRate = (float) ($row->rate ?? 0);
                    $qty = $no_of_units * $total_ctn;
                    $transitSum += $qty;
                    // Calculate value as qty * rate for each row (like transit-container-details page: amount = qty * rate)
                    if ($qty > 0 && $rowRate > 0) {
                        $transitValueSum += ($qty * $rowRate);
                    }
                    if (!empty($row->rate)) {
                        $rate = $rowRate; // Keep last rate (for backward compatibility)
                    }
                }

                return (object)[
                    'tab_name' => $group->pluck('tab_name')->unique()->implode(', '),
                    'transit' => $transitSum,
                    'rate' => $rate,
                    'transit_value' => $transitValueSum, // Total value as sum of (qty * rate) for all rows
                ];
            })
            ->keyBy(fn($item, $key) => $key);

        // Fallback map keyed by canonical SKU to absorb formatting differences between data sources.
        $transitContainerByCanonical = collect($transitContainer)
            ->groupBy(function ($item, $skuKey) use ($canonicalSku) {
                return $canonicalSku($skuKey);
            })
            ->map(function ($group) {
                $transitSum = 0.0;
                $transitValueSum = 0.0;
                $rate = 0.0;
                $tabNames = [];
                foreach ($group as $row) {
                    $transitSum += (float)($row->transit ?? 0);
                    $transitValueSum += (float)($row->transit_value ?? 0);
                    if ((float)($row->rate ?? 0) > 0) {
                        $rate = (float)$row->rate;
                    }
                    $parts = array_filter(array_map('trim', explode(',', (string)($row->tab_name ?? ''))));
                    foreach ($parts as $p) $tabNames[$p] = true;
                }
                return (object)[
                    'tab_name' => implode(', ', array_keys($tabNames)),
                    'transit' => $transitSum,
                    'rate' => $rate,
                    'transit_value' => $transitValueSum,
                ];
            });
        $getTransitContainerForSku = function ($sku) use ($transitContainer, $transitContainerByCanonical, $normalizeSku, $canonicalSku) {
            $n = $normalizeSku($sku);
            if ($n !== '' && isset($transitContainer[$n])) {
                return $transitContainer[$n];
            }
            $c = $canonicalSku($sku);
            if ($c !== '' && $transitContainerByCanonical->has($c)) {
                return $transitContainerByCanonical->get($c);
            }
            return (object)[
                'tab_name' => '',
                'transit' => 0,
                'rate' => 0,
                'transit_value' => 0,
            ];
        };



        $processedData = [];

        foreach ($productListData as $prodData) {
            $sheetSku = $normalizeSku($prodData->sku);
            if (empty($sheetSku)) continue;
            $toOrderMeta = $toOrderMetaBySku->get($sheetSku);

            $item = new \stdClass();
            $item->SKU = $sheetSku;
            $item->Parent = $normalizeSku($prodData->parent ?? '');
            $toOrderKey = $sheetSku . '|' . $item->Parent;
            $item->two_order_qty = (float) (
                $toOrderApprovedBySkuParent->get(
                    $toOrderKey,
                    (float) $toOrderApprovedBySku->get($sheetSku, 0)
                )
            );
            $item->is_parent = stripos($sheetSku, 'PARENT') !== false;
            $item->{'Supplier Tag'} = isset($supplierMapByParent[$item->Parent]) ? implode(', ', array_unique($supplierMapByParent[$item->Parent])) : '';

            $valuesRaw = $prodData->Values ?? '{}';
            $values = json_decode($valuesRaw, true);
            $productMasterMoq = (isset($values['moq']) && $values['moq'] !== '' && is_numeric($values['moq']))
                ? (float) $values['moq']
                : 0.0;

            $item->{'CP'} = $values['cp'] ?? '';
            $item->{'LP'} = $values['lp'] ?? '';
            $item->{'MOQ'} = $values['moq'] ?? '';
            $item->{'SH'} = $values['ship'] ?? '';
            $item->{'Freight'} = $values['frght'] ?? '';
            $item->{'CBM MSL'} = $values['cbm'] ?? '';
            $item->{'GW (LB)'} = $values['wt_act'] ?? '';
            $item->{'GW (KG)'} = is_numeric($values['wt_act'] ?? null) ? round($values['wt_act'] * 0.45, 2) : '';

            $shopify = $shopifyData[$sheetSku] ?? null;
            
            // If exact match fails, try to find by removing all spaces (fallback)
            if (!$shopify && !empty($sheetSku)) {
                $skuNoSpaces = str_replace(' ', '', $sheetSku);
                foreach ($shopifyData as $key => $value) {
                    if (str_replace(' ', '', $key) === $skuNoSpaces) {
                        $shopify = $value;
                        break;
                    }
                }
            }
            
            $imageFromShopify = $shopify ? ($shopify->image_src ?? null) : null;
            $imageFromProductMaster = $values['image_path'] ?? null;
            $item->Image = $imageFromShopify ?: $imageFromProductMaster;

            // Safely get inventory - check if shopify exists first
            $item->INV = ($shopify && isset($shopify->inv)) ? (int)$shopify->inv : 0;
            $item->L30 = ($shopify && isset($shopify->quantity)) ? (int)$shopify->quantity : 0;
            
            // Calculate shopifyb2c_price and inv_value
            $shopifyb2c_price = ($shopify && isset($shopify->price)) ? (float)$shopify->price : 0;
            $item->shopifyb2c_price = $shopifyb2c_price;
            $item->inv_value = $item->INV * $shopifyb2c_price;
            
            // Calculate lp_value (LP * INV)
            $lp = is_numeric($item->{'LP'}) ? (float)$item->{'LP'} : 0;
            $item->lp_value = $lp * $item->INV;

            $adv = $getAmazonAdv($sheetSku);
            $item->avg_gpft_pct = null;
            $item->avg_npft_pct = null;
            $item->avg_nroi_pct = null;
            if (is_array($adv)) {
                $gpft = (float) ($adv['GPFT'] ?? 0);
                $adPct = (float) ($adv['AD_percent'] ?? $adv['AD%'] ?? 0);
                $roiPct = (float) ($adv['ROI'] ?? 0);
                $hasAdv = array_key_exists('GPFT', $adv) || array_key_exists('ROI', $adv)
                    || array_key_exists('AD_percent', $adv) || array_key_exists('AD%', $adv);
                if ($hasAdv || $gpft != 0.0 || $roiPct != 0.0 || $adPct != 0.0) {
                    $item->avg_gpft_pct = (int) round($gpft);
                    $item->avg_npft_pct = (int) round($gpft - $adPct);
                    $item->avg_nroi_pct = (int) round($roiPct - $adPct);
                }
            }
            $item->reviews = $getJungleReviews($sheetSku);
            $item->rating = $getJungleRating($sheetSku);

            // if (!empty($item->Parent) && $jungleScoutData->has($item->Parent)) {
            //     $item->scout_data = json_decode(json_encode($jungleScoutData[$item->Parent]), true);
            // }

            // Initialize nr field to empty string first
            $item->nr = '';
            
            // Match forecast record by SKU only
            if ($forecastMap->has($sheetSku)) {
                $forecast = $forecastMap->get($sheetSku);
                $item->{'s-msl'} = $forecast->s_msl ?? 0;
                $item->{'Approved QTY'} = $forecast->approved_qty ?? 0;
                // MOQ on forecast = same as two-orders (approved_qty) so changes on to-order page show here
                $approved = $forecast->approved_qty ?? null;
                $item->{'MOQ'} = ($approved !== null && $approved !== '') ? $approved : ($item->{'MOQ'} ?? '');
                
                // Get and normalize nr value - preserve the value even if it's empty
                $nrValue = $forecast->nr ?? null;
                if ($nrValue !== null && $nrValue !== '') {
                    $item->nr = strtoupper(trim((string)$nrValue));
                    // Ensure it's a valid value
                    if (!in_array($item->nr, ['REQ', 'NR', 'LATER'])) {
                        $item->nr = 'REQ'; // Default to REQ if invalid
                    }
                } else {
                    $item->nr = ''; // Keep as empty string, formatter will default to REQ
                }
                
                $item->req = $forecast->req ?? '';
                $item->hide = $forecast->hide ?? '';
                $item->notes = $forecast->notes ?? '';
                $item->{'Clink'} = $forecast->clink ?? '';
                $item->{'Olink'} = $forecast->olink ?? '';
                $item->rfq_form_link = trim((string)($forecast->rfq_form_link ?? '')) ?: trim((string)($toOrderMeta->rfq_form_link ?? ''));
                $item->rfq_report = trim((string)($forecast->rfq_report ?? '')) ?: trim((string)($toOrderMeta->rfq_report_link ?? ''));
                $item->sheet_link = trim((string)($toOrderMeta->sheet_link ?? ''));
                $item->date_apprvl = $forecast->date_apprvl ?? '';
                // Normalize stage value: trim and convert to lowercase
                $stageValue = $forecast->stage ?? '';
                $item->stage = !empty($stageValue) ? strtolower(trim($stageValue)) : '';
            } else {
                // If key not found in map, try direct database lookup by SKU only
                $forecastRecord = DB::table('forecast_analysis')
                    ->whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper(trim($sheetSku))])
                    ->orderByRaw("CASE WHEN stage IS NOT NULL AND stage != '' THEN 0 ELSE 1 END")
                    ->orderByRaw("CASE WHEN nr IS NOT NULL AND nr != '' THEN 0 ELSE 1 END")
                    ->first();
                
                if ($forecastRecord) {
                    $item->{'s-msl'} = $forecastRecord->s_msl ?? 0;
                    $item->{'Approved QTY'} = $forecastRecord->approved_qty ?? 0;
                    // MOQ = same as two-orders (approved_qty)
                    $approved = $forecastRecord->approved_qty ?? null;
                    $item->{'MOQ'} = ($approved !== null && $approved !== '') ? $approved : ($item->{'MOQ'} ?? '');
                    
                    // Get and normalize nr value
                    $nrValue = $forecastRecord->nr ?? null;
                    if ($nrValue !== null && $nrValue !== '') {
                        $item->nr = strtoupper(trim((string)$nrValue));
                        // Ensure it's a valid value
                        if (!in_array($item->nr, ['REQ', 'NR', 'LATER'])) {
                            $item->nr = 'REQ'; // Default to REQ if invalid
                        }
                    } else {
                        $item->nr = ''; // Keep as empty string, formatter will default to REQ
                    }
                    
                    $item->req = $forecastRecord->req ?? '';
                    $item->hide = $forecastRecord->hide ?? '';
                    $item->notes = $forecastRecord->notes ?? '';
                    $item->{'Clink'} = $forecastRecord->clink ?? '';
                    $item->{'Olink'} = $forecastRecord->olink ?? '';
                    $item->rfq_form_link = trim((string)($forecastRecord->rfq_form_link ?? '')) ?: trim((string)($toOrderMeta->rfq_form_link ?? ''));
                    $item->rfq_report = trim((string)($forecastRecord->rfq_report ?? '')) ?: trim((string)($toOrderMeta->rfq_report_link ?? ''));
                    $item->sheet_link = trim((string)($toOrderMeta->sheet_link ?? ''));
                    $item->date_apprvl = $forecastRecord->date_apprvl ?? '';
                    // Normalize stage value: trim and convert to lowercase
                    $stageValue = $forecastRecord->stage ?? '';
                    $item->stage = !empty($stageValue) ? strtolower(trim($stageValue)) : '';
                }
            }
            if (!isset($item->rfq_form_link)) $item->rfq_form_link = trim((string)($toOrderMeta->rfq_form_link ?? ''));
            if (!isset($item->rfq_report)) $item->rfq_report = trim((string)($toOrderMeta->rfq_report_link ?? ''));
            if (!isset($item->sheet_link)) $item->sheet_link = trim((string)($toOrderMeta->sheet_link ?? ''));

            $transitForSku = $getTransitContainerForSku($prodData->sku ?? '');
            $item->containerName = $transitForSku->tab_name ?? '';
            $item->transit = $transitForSku->transit ?? 0;
            $item->transit_rate = (float)($transitForSku->rate ?? 0);
            $item->transit_value_calculated = (float)($transitForSku->transit_value ?? 0); // Pre-calculated value from transit_container_details


            $readyToShipQty = 0;
            $r2sRate = 0;
            $r2sHasRecord = false;
            $r2sOrderQty = 0;
            $r2sRecQty = 0;
            $r2sPayment = 'No';
            $r2sPayTerm = '';
            $r2sPackingList = 'No';
            $r2sNewPhoto = 'No';
            $r2sZone = '';
            $readyToShipData = $getReadyToShipForSku($sheetSku);
            if($readyToShipData){
                $r2sHasRecord = true;
                $readyToShipQty = $readyToShipData->qty ?? 0;
                $r2sRate = (float) ($readyToShipData->rate ?? 0);
                $r2sOrderQty = (float) ($readyToShipData->qty ?? 0);
                $r2sRecQty = ($readyToShipData->rec_qty !== null && $readyToShipData->rec_qty !== '')
                    ? (float) $readyToShipData->rec_qty
                    : (float) $r2sOrderQty;
                $r2sPayment = $readyToShipData->payment ?? 'No';
                $r2sPayTerm = $readyToShipData->pay_term ?? '';
                $r2sPackingList = $readyToShipData->packing_list ?? 'No';
                $r2sNewPhoto = $readyToShipData->photo_mail_send ?? 'No';
                $item->readyToShipQty = $readyToShipQty;
                $r2sZone = trim((string) ($readyToShipData->area ?? ''));
            }
            $item->r2s_rate = $r2sRate;
            $item->r2s_has_record = $r2sHasRecord;
            $item->r2s_order_qty = $r2sOrderQty;
            $item->r2s_rec_qty = $r2sRecQty;
            $item->r2s_payment = $r2sPayment;
            $item->r2s_pay_term = $r2sPayTerm;
            $item->r2s_packing_list = $r2sPackingList;
            $item->r2s_new_photo = $r2sNewPhoto;
            $item->r2s_zone = $r2sZone;

            // MIP column should ONLY come from mfrg_progress table, no fallback to purchases
            $order_given = 0;
            $mipRate = 0;
            $mfrgReadyToShip = 'No'; // Default to 'No'
            $mfrgSupplier = '';
            $mfrgQtyRaw = 0;
            $mfrgPkgInst = 'No';
            $mfrgUManual = 'No';
            $mfrgCompliance = 'No';
            $mfrgOrderDate = '';
            if($mfrg->has($sheetSku)){
                $mfrgData = $mfrg->get($sheetSku);
                $mfrgSupplier = trim($mfrgData->supplier ?? '') ?: '';
                $mfrgReadyToShip = $mfrgData->ready_to_ship ?? 'No';
                $mfrgQtyRaw = (float) ($mfrgData->qty ?? 0);
                $mfrgPkgInst = $mfrgData->pkg_inst ?? 'No';
                $mfrgUManual = $mfrgData->u_manual ?? 'No';
                $mfrgCompliance = $mfrgData->compliance ?? 'No';
                $mfrgOrderDate = !empty($mfrgData->created_at) ? (string)$mfrgData->created_at : '';
                if($mfrgReadyToShip === 'No' || $mfrgReadyToShip === ''){
                    $order_given = (float) ($mfrgData->qty ?? 0);
                    $mipRate = (float) ($mfrgData->rate ?? 0);
                }
            }
            // Removed purchases table fallback - MIP should only show mfrg_progress data
            $item->order_given = $order_given;
            $item->mip_rate = $mipRate; // Store rate for MIP Value calculation
            $item->mfrg_ready_to_ship = $mfrgReadyToShip; // Store ready_to_ship status from mfrg_progress table
            $item->mfrg_supplier = $mfrgSupplier; // Supplier from mfrg_progress (same as MIP blade)
            $item->mfrg_qty = $mfrgQtyRaw;
            $item->pkg_inst = $mfrgPkgInst;
            $item->u_manual = $mfrgUManual;
            $item->compliance = $mfrgCompliance;
            $item->mfrg_order_date = $mfrgOrderDate;
            $cbmNum = is_numeric($item->{'CBM MSL'} ?? null) ? (float)($item->{'CBM MSL'}) : 0.0;
            $item->cbm = $cbmNum;
            $item->total_cbm = round($mfrgQtyRaw * $cbmNum, 4);

            if ($movementMap->has($sheetSku)) {
                $months = json_decode($movementMap->get($sheetSku)->months ?? '{}', true);
                $months = is_array($months) ? $months : [];

                $monthNames = ['Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov'];
                $totalMonthCount = 0;
                $totalSum = 0;

                foreach ($monthNames as $month) {
                    $value = isset($months[$month]) && is_numeric($months[$month]) ? (int)$months[$month] : 0;
                    $item->{$month} = $value;
                    if ($value !== 0) $totalMonthCount++;
                    $totalSum += $value;
                }

                $item->{'Total'} = $totalSum;
                $item->{'Total month'} = $totalMonthCount;
                
                $msl = $item->{'Total month'} > 0 ? ($item->{'Total'} / $item->{'Total month'}) * 4 : 0;

                $effectiveMsl = (isset($item->{'s-msl'}) && $item->{'s-msl'} > 0) ? $item->{'s-msl'} : $msl;
                
                $lp = is_numeric($item->{'LP'}) ? (float)$item->{'LP'} : 0;
                $item->{'MSL_C'} = round($msl * $lp / 4, 2);

                $mslfour = $msl/4;

                $item->{'MSL_Four'} = round($msl / 4, 2);

                $item->{'MSL_SP'} = floor($shopifyb2c_price * $effectiveMsl / 4);

                $amzPrc = $resolveAmazonPrice($sheetSku);
                $item->amz_prc = $amzPrc;
                $item->{'MSL_SP_AMZ'} = round($msl * $amzPrc / 4, 2);

                $item->msl = (int) round($msl);
                // M AVG = average monthly movement (same months as MSL: sum of movement / count of non-zero months)
                $mAvg = $totalMonthCount > 0 ? ($totalSum / $totalMonthCount) : 0.0;
                $item->m_avg = round($mAvg, 6);
                $moqVal = is_numeric($item->{'MOQ'} ?? null) ? (float) $item->{'MOQ'} : (float) preg_replace('/[^0-9.\-]/', '', (string) ($item->{'MOQ'} ?? ''));
                $item->TAT = $mAvg > 0 ? (int) round($moqVal / $mAvg) : null;
            } else {
                $item->msl = 0;
                $item->amz_prc = $resolveAmazonPrice($sheetSku);
                $item->{'MSL_SP_AMZ'} = 0;
                $item->m_avg = 0.0;
                $item->TAT = null;
            }

            $item->eff_roi_pct = null;
            $tatInt = $item->TAT ?? null;
            $nroiForEff = $item->avg_nroi_pct;
            if ($tatInt !== null && (int) $tatInt > 0 && $nroiForEff !== null && is_numeric($nroiForEff)) {
                $item->eff_roi_pct = (int) round(((float) $nroiForEff / (int) $tatInt) * 12);
            }

            $cp = (float)($item->{'CP'} ?? 0);
            $orderQty = (float)($item->order_given ?? 0);
            $readyToShipQty = (float)($item->readyToShipQty ?? 0);
            $transit = (float)($transitForSku->transit ?? 0);
            $transitRate = (float)($transitForSku->rate ?? 0);
            $transitValueCalculated = (float)($transitForSku->transit_value ?? 0);

            // MIP Value: Use qty * rate from mfrg_progress (like mfrg-in-progress page), fallback to CP * order_given if rate not available
            // For items with stage === 'mip', use rate * qty (matching mfrg-in-progress page calculation)
            if ($mipRate > 0 && $orderQty > 0) {
                $item->MIP_Value = round($mipRate * $orderQty, 2);
            } else {
                // Fallback to CP * order_given if rate not available
                $item->MIP_Value = round($cp * $orderQty, 2);
            }
            
            // R2S Value: Use qty * rate from ready_to_ship table (like ready-to-ship page), fallback to CP * readyToShipQty if rate not available
            // For items with stage === 'r2s', use rate * qty (matching ready-to-ship page calculation)
            if ($r2sRate > 0 && $readyToShipQty > 0) {
                $item->R2S_Value = round($r2sRate * $readyToShipQty, 2);
            } else {
                // Fallback to CP * readyToShipQty if rate not available
                $item->R2S_Value = round($cp * $readyToShipQty, 2);
            }
            
            // Transit Value: Use qty * rate from transit_container_details (like transit-container-details page), fallback to CP * transit if rate not available
            // In transit-container-details page: amount = (no_of_units * total_ctn) * rate for each row, then sum all rows
            // We've pre-calculated transit_value as sum of (qty * rate) for all rows with same SKU
            if ($transitValueCalculated > 0) {
                // Use pre-calculated value (sum of all (qty * rate) for this SKU from transit_container_details)
                $item->Transit_Value = round($transitValueCalculated, 2);
            } else if ($transit > 0 && $transitRate > 0) {
                // Fallback: if we have qty and rate, calculate directly
                $item->Transit_Value = round($transit * $transitRate, 2);
            } else {
                // Final fallback: CP * transit (for backward compatibility)
                $item->Transit_Value = round($cp * $transit, 2);
            }
            $item->r2s_amount = round(((float) $r2sOrderQty) * $cp, 0);

            // Stage from pipeline qtys: Transit → R2S → MIP → 2 Order; none → Select (empty)
            // Never auto-overwrite appr_req — this is set from the grid and must survive refresh.
            if (! $item->is_parent) {
                $currentStage = strtolower(trim((string) ($item->stage ?? '')));
                $manualStages = ['appr_req'];

                if (! in_array($currentStage, $manualStages, true)) {
                    $qtyTransit = (float) ($item->transit ?? 0);
                    $qtyR2s = (float) ($item->readyToShipQty ?? 0);
                    $qtyMip = (float) ($item->order_given ?? 0);
                    // 2 Order column = to_order_analysis approved qty only (not Appr.req MOQ)
                    $qtyTwoOrder = (float) ($item->two_order_qty ?? 0);
                    if ($qtyTransit > 0) {
                        $derivedStage = 'transit';
                    } elseif ($qtyR2s > 0) {
                        $derivedStage = 'r2s';
                    } elseif ($qtyMip > 0) {
                        $derivedStage = 'mip';
                    } elseif ($qtyTwoOrder > 0) {
                        $derivedStage = 'to_order_analysis';
                    } else {
                        $derivedStage = '';
                    }
                    if ($currentStage !== $derivedStage) {
                        DB::table('forecast_analysis')
                            ->whereRaw('TRIM(LOWER(sku)) = ?', [strtolower($sheetSku)])
                            ->update(['stage' => $derivedStage, 'updated_at' => now()]);
                    }
                    $item->stage = $derivedStage;
                }
            }

            $item->product_master_moq = $productMasterMoq;

            $processedData[] = $item;
        }

        return $processedData;
    }

    public function getViewForecastAnalysisData()
    {
        try {
            $processedData = $this->buildForecastAnalysisData();

            $totalMslC = collect($processedData)
                ->filter(function ($item) {
                    return !$item->is_parent;
                })
                ->sum(function ($item) {
                    return floatval($item->{'MSL_C'} ?? 0);
                });

            $totalMslSp = collect($processedData)
                ->filter(function ($item) {
                    return !$item->is_parent;
                })
                ->sum(function ($item) {
                    return floatval($item->{'MSL_SP'} ?? 0);
                });

            $totalMslSpAmz = collect($processedData)
                ->filter(function ($item) {
                    return ! $item->is_parent;
                })
                ->sum(function ($item) {
                    return floatval($item->{'MSL_SP_AMZ'} ?? 0);
                });

            // Trn Val: sum of (transit QTY × CP) per SKU (child rows only).
            // Transit qty matches the grid (aggregated units×ctn per SKU, warehouse-pushed lines excluded).
            $totalTransitValue = collect($processedData)
                ->filter(function ($item) {
                    return ! $item->is_parent;
                })
                ->sum(function ($item) {
                    $transitQty = (float) ($item->transit ?? 0);
                    $cp = (float) ($item->{'CP'} ?? 0);

                    return $transitQty * $cp;
                });

            return response()->json([
                'message' => 'Data fetched successfully',
                'data' => $processedData,
                'total_msl_c' => round($totalMslC, 2),
                'total_msl_sp' => round($totalMslSp, 0),
                'total_msl_sp_amz' => round($totalMslSpAmz, 2),
                'total_transit_value' => round($totalTransitValue, 2), // Sum(transit QTY × CP) for forecast child SKUs
                'status' => 200,
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Something went wrong!',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function forecastAnalysis(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        return view('purchase-master.forecastAnalysis', [
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function approvalRequired(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        return view('purchase-master.approvalRequired', [
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function transit(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        return view('purchase-master.transit', [
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    /**
     * Link a supplier to a parent (add parent to supplier's Parents list). Used from forecast analysis Supplier dropdown.
     */
    public function linkSupplierToParent(Request $request)
    {
        $supplierId = (int) $request->input('supplier_id');
        $parent = strtoupper(trim($request->input('parent') ?? ''));
        if (!$supplierId || $parent === '') {
            return response()->json(['success' => false, 'message' => 'Supplier and parent are required.']);
        }
        $supplier = Supplier::find($supplierId);
        if (!$supplier) {
            return response()->json(['success' => false, 'message' => 'Supplier not found.']);
        }
        $current = array_map('trim', explode(',', $supplier->parent ?? ''));
        $current = array_filter($current);
        $parentNormalized = strtoupper(trim($parent));
        if (!in_array($parentNormalized, array_map('strtoupper', $current))) {
            $current[] = $parent;
            $supplier->parent = implode(', ', $current);
            $supplier->save();
        }
        return response()->json(['success' => true, 'message' => 'Supplier linked to parent.', 'supplier_tag' => $supplier->name]);
    }

    public function updateForcastSheet(Request $request)
    {        
        $sku = trim((string) $request->input('sku'));
        $parent = trim((string) $request->input('parent'));
        $column = trim((string) $request->input('column'));
        $value = trim((string) $request->input('value', '')); // Avoid trim(null) on numeric-only posts

        // Handle MOQ updates: save to forecast_analysis.approved_qty and to_order_analysis so forecast and to-order show same value
        if (strtoupper($column) === 'MOQ') {
            $skuUpper = strtoupper(trim($sku));
            $parentNorm = strtoupper(trim($parent));
            $valueNum = is_numeric($value) ? (int) $value : null;

            $faUpdated = 0;
            if ($parentNorm !== '') {
                $faUpdated = (int) DB::table('forecast_analysis')
                    ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuUpper])
                    ->whereRaw('TRIM(UPPER(COALESCE(parent, \'\'))) = ?', [$parentNorm])
                    ->update(['approved_qty' => $valueNum, 'updated_at' => now()]);
            }
            if ($faUpdated === 0) {
                $faUpdated = (int) DB::table('forecast_analysis')
                    ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuUpper])
                    ->update(['approved_qty' => $valueNum, 'updated_at' => now()]);
            }
            if ($faUpdated === 0 && $sku !== '') {
                DB::table('forecast_analysis')->insert([
                    'sku' => $sku,
                    'parent' => $parent !== '' ? $parent : null,
                    'approved_qty' => $valueNum,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $toUpdated = 0;
            if ($parentNorm !== '') {
                $toUpdated = (int) DB::table('to_order_analysis')
                    ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuUpper])
                    ->whereRaw('TRIM(UPPER(COALESCE(parent, \'\'))) = ?', [$parentNorm])
                    ->update(['approved_qty' => $valueNum]);
            }
            if ($toUpdated === 0) {
                $toUpdated = (int) DB::table('to_order_analysis')
                    ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuUpper])
                    ->update(['approved_qty' => $valueNum]);
            }

            // Optionally keep product_master.Values['moq'] in sync
            $product = ProductMaster::whereRaw('TRIM(LOWER(sku)) = ?', [strtolower($sku)])->first();
            if ($product) {
                $values = is_array($product->Values) ? $product->Values : (json_decode($product->Values, true) ?? []);
                $values['moq'] = $value;
                $product->Values = $values;
                $product->save();
            }

            return response()->json(['success' => true, 'message' => 'MOQ updated successfully']);
        }

        // Handle CP updates: save to product_master Values->cp
        if (strtoupper($column) === 'CP') {
            $valueNum = is_numeric($value) ? (float) $value : null;
            $product = ProductMaster::whereRaw('TRIM(LOWER(sku)) = ?', [strtolower($sku)])->first();
            if ($product) {
                $values = is_array($product->Values) ? $product->Values : (json_decode($product->Values ?? '{}', true) ?? []);
                $values['cp'] = $valueNum !== null ? $valueNum : '';
                $product->Values = $values;
                $product->save();
                return response()->json(['success' => true, 'message' => 'CP updated successfully']);
            }
            return response()->json(['success' => false, 'message' => 'Product not found']);
        }

        // Handle 2-Order updates: persist to to_order_analysis.approved_qty
        if (strtoupper($column) === 'ORDER') {
            if (!is_numeric($value)) {
                return response()->json(['success' => false, 'message' => 'Invalid order value']);
            }

            $valueNum = (float) $value;
            if ($valueNum < 0) {
                return response()->json(['success' => false, 'message' => 'Order cannot be negative']);
            }

            $skuUpper = strtoupper(trim($sku));
            $parentNorm = strtoupper(trim($parent));

            $updated = 0;
            if ($parentNorm !== '') {
                $updated = (int) DB::table('to_order_analysis')
                    ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuUpper])
                    ->whereRaw('TRIM(UPPER(COALESCE(parent, \'\'))) = ?', [$parentNorm])
                    ->update([
                        'approved_qty' => $valueNum,
                        'date_apprvl' => now()->toDateString(),
                        'auth_user' => optional(Auth::user())->name,
                        'updated_at' => now(),
                        'deleted_at' => null,
                    ]);
            }

            if ($updated === 0) {
                $updated = (int) DB::table('to_order_analysis')
                    ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuUpper])
                    ->update([
                        'approved_qty' => $valueNum,
                        'date_apprvl' => now()->toDateString(),
                        'auth_user' => optional(Auth::user())->name,
                        'updated_at' => now(),
                        'deleted_at' => null,
                    ]);
            }

            if ($updated === 0 && $sku !== '') {
                DB::table('to_order_analysis')->insert([
                    'sku' => $sku,
                    'parent' => $parent !== '' ? $parent : null,
                    'approved_qty' => $valueNum,
                    'date_apprvl' => now()->toDateString(),
                    'stage' => '',
                    'auth_user' => optional(Auth::user())->name,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ]);
            }

            return response()->json(['success' => true, 'message' => 'Order updated successfully']);
        }

        // Handle MIP updates: persist to mfrg_progress.qty (this is the page source for MIP column)
        if (strtoupper($column) === 'ORDER_GIVEN' || strtoupper($column) === 'MIP') {
            if (!is_numeric($value)) {
                return response()->json(['success' => false, 'message' => 'Invalid MIP value']);
            }

            $valueNum = (float) $value;
            if ($valueNum < 0) {
                return response()->json(['success' => false, 'message' => 'MIP cannot be negative']);
            }

            $skuUpper = strtoupper(trim($sku));
            $parentNorm = strtoupper(trim($parent));

            $updated = 0;
            if ($parentNorm !== '') {
                $updated = (int) DB::table('mfrg_progress')
                    ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuUpper])
                    ->whereRaw('TRIM(UPPER(COALESCE(parent, \'\'))) = ?', [$parentNorm])
                    ->update([
                        'qty' => $valueNum,
                        'ready_to_ship' => 'No',
                        'updated_at' => now(),
                    ]);
            }

            if ($updated === 0) {
                $updated = (int) DB::table('mfrg_progress')
                    ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuUpper])
                    ->update([
                        'qty' => $valueNum,
                        'ready_to_ship' => 'No',
                        'updated_at' => now(),
                    ]);
            }

            if ($updated === 0 && $sku !== '') {
                DB::table('mfrg_progress')->insert([
                    'sku' => $sku,
                    'parent' => $parent !== '' ? $parent : null,
                    'qty' => $valueNum,
                    'ready_to_ship' => 'No',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return response()->json(['success' => true, 'message' => 'MIP updated successfully']);
        }

        // Handle R2S updates: persist to ready_to_ship.qty
        if (strtoupper($column) === 'R2S' || strtoupper($column) === 'READYTOSHIPQTY') {
            if (!is_numeric($value)) {
                return response()->json(['success' => false, 'message' => 'Invalid R2S value']);
            }

            $valueNum = (float) $value;
            if ($valueNum < 0) {
                return response()->json(['success' => false, 'message' => 'R2S cannot be negative']);
            }

            $skuUpper = strtoupper(trim($sku));
            $parentNorm = strtoupper(trim($parent));

            $updated = (int) DB::table('ready_to_ship')
                ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuUpper])
                ->whereRaw('TRIM(UPPER(COALESCE(parent, \'\'))) = ?', [$parentNorm])
                ->whereNull('deleted_at')
                ->update([
                    'qty'        => $valueNum,
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                $updated = (int) DB::table('ready_to_ship')
                    ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuUpper])
                    ->whereNull('deleted_at')
                    ->update([
                        'qty'        => $valueNum,
                        'updated_at' => now(),
                    ]);
            }

            if ($updated === 0 && $sku !== '') {
                DB::table('ready_to_ship')->insert([
                    'sku'                => $sku,
                    'parent'             => $parent !== '' ? $parent : null,
                    'qty'                => $valueNum,
                    'transit_inv_status' => 0,
                    'auth_user'          => optional(Auth::user())->name,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
            }

            return response()->json(['success' => true, 'message' => 'R2S updated successfully']);
        }

        $columnMap = [
            'Approved QTY' => 'approved_qty',
            'NR' => 'nr',
            'REQ' => 'req',
            'Hide' => 'hide',
            'Notes' => 'notes',
            'Clink' => 'clink',
            'Olink' => 'olink',
            'rfq_form_link' => 'rfq_form_link',
            'rfq_report' => 'rfq_report',
            'order_given' => 'order_given',
            'Transit' => 'transit',
            'Date of Appr' => 'date_apprvl',
            'Stage' => 'stage',
        ];

        $columnKey = $columnMap[$column] ?? null;

        if (!$columnKey) {
            return response()->json(['success' => false, 'message' => 'Invalid column']);
        }
        if ($columnKey === 's_msl') {
            $value = mb_substr((string) $value, 0, 4);
        }

        // Match by SKU only - prefer record with stage value if multiple exist
        $existing = DB::table('forecast_analysis')
            ->select('*')
            ->whereRaw('TRIM(LOWER(sku)) = ?', [strtolower($sku)])
            ->orderByRaw("CASE WHEN stage IS NOT NULL AND stage != '' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN nr IS NOT NULL AND nr != '' THEN 0 ELSE 1 END")
            ->first();

        if ($existing) {
            $currentValue = $existing->{$columnKey} ?? null;
            
            // Normalize NR values to uppercase
            if ($columnKey === 'nr' && !empty($value)) {
                $value = strtoupper(trim($value));
                // Ensure value is one of the valid options
                if (!in_array($value, ['REQ', 'NR', 'LATER'])) {
                    $value = 'REQ'; // Default to REQ if invalid
                }
            }

            // Normalize Stage values to lowercase
            if ($columnKey === 'stage' && !empty($value)) {
                $value = strtolower(trim($value));
                // Ensure value is one of the valid options
                $validStages = ['appr_req', 'mip', 'r2s', 'transit', 'to_order_analysis'];
                if (!in_array($value, $validStages)) {
                    $value = ''; // Default to empty if invalid
                }
            }

            if ((string)$currentValue !== (string)$value) {
                DB::table('forecast_analysis')
                    ->where('id', $existing->id)
                    ->update([$columnKey => $value, 'updated_at' => now()]);
            }

            if (strtolower($column) === 'stage'){
                // Get MOQ from ProductMaster table (not from approved_qty)
                $product = ProductMaster::whereRaw('TRIM(LOWER(sku)) = ?', [strtolower($sku)])->first();
                $moqValue = null;
                if ($product && $product->Values) {
                    $values = is_array($product->Values) ? $product->Values : json_decode($product->Values, true);
                    $moqValue = $values['moq'] ?? null;
                }
                
                // Use MOQ value from ProductMaster
                $orderQty = $moqValue ?? null;

                if (strtolower($value) === 'appr_req' && is_numeric($orderQty) && (float) $orderQty > 0) {
                    DB::table('forecast_analysis')
                        ->whereRaw('TRIM(LOWER(sku)) = ?', [strtolower($sku)])
                        ->whereRaw('TRIM(LOWER(parent)) = ?', [strtolower($parent)])
                        ->update([
                            'approved_qty' => (float) $orderQty,
                            'updated_at' => now(),
                        ]);
                }
                
                if(strtolower($value) === 'to_order_analysis'){
                    DB::table('to_order_analysis')->updateOrInsert(
                        ['sku' => $sku, 'parent' => $parent],
                        [
                            'approved_qty' => $orderQty,
                            'date_apprvl' => now()->toDateString(),
                            'stage' => '',
                            'auth_user' => Auth::user()->name,
                            'updated_at' => now(),
                            'created_at' => now(),
                            'deleted_at' => null,
                        ]
                    );
                }

                if(strtolower($value) === 'mip'){
                    // Always use MOQ value from ProductMaster
                    $qtyToSet = (int)($orderQty ?? 0);
                    
                    // Check if record exists - match by SKU only
                    $existingMfrg = DB::table('mfrg_progress')
                        ->whereRaw('TRIM(LOWER(sku)) = ?', [strtolower($sku)])
                        ->orderByRaw("CASE WHEN ready_to_ship = 'No' THEN 0 ELSE 1 END")
                        ->first();
                    
                    if ($existingMfrg) {
                        // Update existing record - explicitly set qty to MOQ value
                        DB::table('mfrg_progress')
                            ->where('id', $existingMfrg->id)
                            ->update([
                                'qty' => $qtyToSet,
                                'ready_to_ship' => 'No',
                                'parent' => $parent, // Update parent if different
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                    } else {
                        // Insert new record
                        DB::table('mfrg_progress')->insert([
                            'sku' => $sku,
                            'parent' => $parent,
                            'qty' => $qtyToSet,
                            'ready_to_ship' => 'No',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                if(strtolower($value) === 'r2s'){
                    DB::table('ready_to_ship')->updateOrInsert(
                        ['sku' => $sku, 'parent' => $parent],
                        [
                            'qty' => $orderQty,
                            'transit_inv_status' => 0,
                            'auth_user' => Auth::user()->name,
                            'updated_at' => now(),
                            'created_at' => now(),
                            'deleted_at' => null,
                        ]
                    );
                }
            }

            return response()->json(['success' => true, 'message' => 'Updated or already up-to-date']);
        } else {
            // Normalize NR values to uppercase before inserting
            if ($columnKey === 'nr' && !empty($value)) {
                $value = strtoupper(trim($value));
                // Ensure value is one of the valid options
                if (!in_array($value, ['REQ', 'NR', 'LATER'])) {
                    $value = 'REQ'; // Default to REQ if invalid
                }
            }

            // Normalize Stage values to lowercase before inserting
            if ($columnKey === 'stage' && !empty($value)) {
                $value = strtolower(trim($value));
                // Ensure value is one of the valid options
                $validStages = ['appr_req', 'mip', 'r2s', 'transit', 'to_order_analysis'];
                if (!in_array($value, $validStages)) {
                    $value = ''; // Default to empty if invalid
                }
            }
            
            DB::table('forecast_analysis')->insert([
                'sku' => $sku,
                'parent' => $parent,
                $columnKey => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (strtolower($column) === 'stage'){
                // Get MOQ from ProductMaster table (not from approved_qty)
                $product = ProductMaster::whereRaw('TRIM(LOWER(sku)) = ?', [strtolower($sku)])->first();
                $moqValue = null;
                if ($product && $product->Values) {
                    $values = is_array($product->Values) ? $product->Values : json_decode($product->Values, true);
                    $moqValue = $values['moq'] ?? null;
                }
                
                // Use MOQ value from ProductMaster
                $orderQty = $moqValue ?? null;

                if (strtolower($value) === 'appr_req' && is_numeric($orderQty) && (float) $orderQty > 0) {
                    DB::table('forecast_analysis')
                        ->whereRaw('TRIM(LOWER(sku)) = ?', [strtolower($sku)])
                        ->whereRaw('TRIM(LOWER(parent)) = ?', [strtolower($parent)])
                        ->update([
                            'approved_qty' => (float) $orderQty,
                            'updated_at' => now(),
                        ]);
                }
                    
                if(strtolower($value) === 'to_order_analysis'){
                    DB::table('to_order_analysis')->updateOrInsert(
                        ['sku' => $sku, 'parent' => $parent],
                        [
                            'approved_qty' => $orderQty,
                            'date_apprvl' => now()->toDateString(),
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }

                if(strtolower($value) === 'mip'){
                    // Always use MOQ value from ProductMaster
                    $qtyToSet = (int)($orderQty ?? 0);
                    
                    // Check if record exists - match by SKU only
                    $existingMfrg = DB::table('mfrg_progress')
                        ->whereRaw('TRIM(LOWER(sku)) = ?', [strtolower($sku)])
                        ->orderByRaw("CASE WHEN ready_to_ship = 'No' THEN 0 ELSE 1 END")
                        ->first();
                    
                    if ($existingMfrg) {
                        // Update existing record - explicitly set qty to MOQ value
                        DB::table('mfrg_progress')
                            ->where('id', $existingMfrg->id)
                            ->update([
                                'qty' => $qtyToSet,
                                'ready_to_ship' => 'No',
                                'parent' => $parent, // Update parent if different
                                'updated_at' => now(),
                            ]);
                    } else {
                        // Insert new record
                        DB::table('mfrg_progress')->insert([
                            'sku' => $sku,
                            'parent' => $parent,
                            'qty' => $qtyToSet,
                            'ready_to_ship' => 'No',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                if(strtolower($value) === 'r2s'){
                    DB::table('ready_to_ship')->updateOrInsert(
                        ['sku' => $sku, 'parent' => $parent],
                        [
                            'qty' => $orderQty,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }

            return response()->json(['success' => true, 'message' => 'Inserted new row']);
        }
    }

    public function getSkuQuantity(Request $request)
    {
        try {
            $sku = $request->query('sku');
            $table = $request->query('table');

            if (!$sku) {
                return response()->json([
                    'success' => false,
                    'message' => 'SKU is required'
                ], 400);
            }

            // Normalize SKU
            $normalizeSku = fn($sku) => strtoupper(trim($sku));
            $normalizedSku = $normalizeSku($sku);

            $exists = false;
            $quantity = 0;

            switch ($table) {
                case 'mfrg-progress':
                    $mfrg = MfrgProgress::whereRaw('UPPER(TRIM(sku)) = ?', [$normalizedSku])->first();
                    if ($mfrg) {
                        $exists = true;
                        $quantity = floatval($mfrg->qty) ?: 0;
                    }
                    break;

                case 'ready-to-ship':
                    $r2s = ReadyToShip::whereRaw('UPPER(TRIM(sku)) = ?', [$normalizedSku])->first();
                    if ($r2s) {
                        $exists = true;
                        $quantity = floatval($r2s->rec_qty) ?: 0;
                    }
                    break;

                case 'transit':
                    $transit = TransitContainerDetail::whereRaw('UPPER(TRIM(sku)) = ?', [$normalizedSku])
                        ->whereNull('deleted_at')
                        ->first();
                    if ($transit) {
                        $exists = true;
                        $quantity = floatval($transit->qty) ?: 0;
                    }
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid table name'
                    ], 400);
            }

            return response()->json([
                'success' => true,
                'exists' => $exists,
                'quantity' => $quantity
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong!',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
