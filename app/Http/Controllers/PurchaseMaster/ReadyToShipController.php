<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\MfrgProgress;
use App\Models\ProductMaster;
use App\Models\ReadyToShip;
use App\Models\Supplier;
use App\Models\TransitContainerDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ReadyToShipController extends Controller
{
    public function index()
    {
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

        foreach ($supplierMapByParent as $parent => $suppliers) {
            $supplierMapByParent[$parent] = array_unique($suppliers);
        }

        $shopifyImages = DB::table('shopify_skus')
            ->select('sku', 'image_src')
            ->get()
            ->keyBy(fn($item) => strtoupper(trim($item->sku)));

        $productMaster = DB::table('product_master')
            ->get()
            ->keyBy(fn($item) => strtoupper(trim($item->sku)));

        // Improved normalization to handle multiple spaces and hidden whitespace
        $normalizeSku = function ($sku) {
            if (empty($sku)) return '';
            $sku = strtoupper(trim($sku));
            $sku = preg_replace('/\s+/u', ' ', $sku);         // collapse multiple spaces to single space
            $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);  // remove hidden whitespace characters
            return trim($sku);
        };

        // Get stage data from forecast_analysis table - match by SKU only, prefer record with stage value
        $forecastData = DB::table('forecast_analysis')
            ->get()
            ->groupBy(function($item) use ($normalizeSku) {
                return $normalizeSku($item->sku);
            })
            ->map(function($group) {
                // Prefer record with non-empty stage value
                $withStage = $group->firstWhere('stage', '!=', null);
                if ($withStage && !empty(trim($withStage->stage))) {
                    return $withStage;
                }
                return $group->first();
            });

        $readyToShipData = ReadyToShip::where('transit_inv_status', 0)->whereNull('deleted_at')->get();

        $readyToShipData = $readyToShipData->filter(function ($item) use ($forecastData, $normalizeSku) {
            $sku = $normalizeSku($item->sku);
            // Only include SKUs where stage is 'r2s' in forecast_analysis
            if ($forecastData->has($sku)) {
                $forecast = $forecastData->get($sku);
                $stage = strtolower(trim($forecast->stage ?? ''));
                return $stage === 'r2s';
            }
            // If no forecast record found, exclude it
            return false;
        });

        $readyToShipData->transform(function ($item) use ($supplierMapByParent, $shopifyImages, $productMaster, $forecastData, $normalizeSku) {
            $sku = $normalizeSku($item->sku);
            $parent = strtoupper(trim($item->parent ?? ''));
            $item->supplier_names = $supplierMapByParent[$parent] ?? [];

            $image = null;
            $cbm = null;
            $cp = null;

            // Try to get image from shopify_skus first
            if (isset($shopifyImages[$sku]) && !empty($shopifyImages[$sku]->image_src)) {
                $image = $shopifyImages[$sku]->image_src;
            }

            // If still no image, try direct database lookup with flexible matching
            if (empty($image) && !empty($item->sku)) {
                try {
                    // Try exact match first
                    $directImage = DB::table('shopify_skus')
                        ->whereRaw('UPPER(TRIM(sku)) = ?', [$sku])
                        ->whereNotNull('image_src')
                        ->where('image_src', '!=', '')
                        ->value('image_src');
                    
                    // If no exact match, try pattern matching
                    if (empty($directImage)) {
                        $skuPattern = str_replace(' ', '%', $sku);
                        $directImage = DB::table('shopify_skus')
                            ->whereRaw('UPPER(TRIM(sku)) LIKE ?', ['%' . $skuPattern . '%'])
                            ->whereNotNull('image_src')
                            ->where('image_src', '!=', '')
                            ->value('image_src');
                    }
                    
                    // If still no match, try without spaces
                    if (empty($directImage)) {
                        $skuNoSpaces = str_replace(' ', '', $sku);
                        $directImage = DB::table('shopify_skus')
                            ->whereRaw('UPPER(REPLACE(sku, " ", "")) = ?', [$skuNoSpaces])
                            ->whereNotNull('image_src')
                            ->where('image_src', '!=', '')
                            ->value('image_src');
                    }
                    
                    if (!empty($directImage)) {
                        $image = $directImage;
                    }
                } catch (\Exception $e) {
                    // Silently fail if database query has issues
                    Log::warning('Image lookup query failed for SKU: ' . $item->sku, ['error' => $e->getMessage()]);
                }
            }

            // Try to get image from product_master if still not found
            if (empty($image)) {
                // Try multiple SKU variations
                $skuVariations = [
                    $sku,
                    str_replace(' ', '', $sku),
                    str_replace(' ', ' ', $sku),
                ];

                $productRow = null;
                foreach ($skuVariations as $skuVar) {
                    if (isset($productMaster[$skuVar])) {
                        $productRow = $productMaster[$skuVar];
                        break;
                    }
                }
                
                // If still no product master, try direct database lookup
                if (!$productRow && !empty($item->sku)) {
                    try {
                        $directProduct = DB::table('product_master')
                            ->whereRaw('UPPER(TRIM(sku)) = ?', [$sku])
                            ->orWhereRaw('UPPER(TRIM(sku)) LIKE ?', ['%' . str_replace(' ', '%', $sku) . '%'])
                            ->first();
                        
                        if ($directProduct) {
                            $productRow = $directProduct;
                        }
                    } catch (\Exception $e) {
                        Log::warning('Product master lookup query failed for SKU: ' . $item->sku, ['error' => $e->getMessage()]);
                    }
                }

                if ($productRow) {
                    $valuesRaw = $productRow->Values ?? '{}';
                    $values = json_decode($valuesRaw, true);

                    if (is_array($values)) {
                        if (!empty($values['image_path']) && empty($image)) {
                            $image = 'storage/' . ltrim($values['image_path'], '/');
                        }

                        if (isset($values['cbm'])) {
                            $cbm = (float) $values['cbm'];
                        } else {
                            Log::warning("CBM missing in values for SKU: $sku");
                        }

                        if (isset($values['cp'])) {
                            $cp = (float) $values['cp'];
                        }
                    } else {
                        Log::warning("Values decode failed for SKU: $sku");
                    }
                } else {
                    Log::warning("SKU missing in product_master: [$sku] <- original: [{$item->sku}]");
                }
            } else {
                // Image found from shopify_skus, but still need to get CBM from product_master
                if (isset($productMaster[$sku])) {
                    $valuesRaw = $productMaster[$sku]->Values ?? '{}';
                    $values = json_decode($valuesRaw, true);

                    if (is_array($values)) {
                        if (isset($values['cbm'])) {
                            $cbm = (float) $values['cbm'];
                        } else {
                            Log::warning("CBM missing in values for SKU: $sku");
                        }

                        if (isset($values['cp'])) {
                            $cp = (float) $values['cp'];
                        }
                    }
                }
            }

            // Get stage and nr from forecast_analysis
            $stage = '';
            $nr = '';
            if ($forecastData->has($sku)) {
                $forecast = $forecastData->get($sku);
                $stage = $forecast->stage ?? '';
                $nr = strtoupper(trim($forecast->nr ?? ''));
                // Normalize stage value to lowercase
                if (!empty($stage)) {
                    $stage = strtolower(trim($stage));
                }
            }
            $item->stage = $stage;
            $item->nr = $nr;
            $item->order_qty = $item->qty; // Add order_qty field for validation

            $item->Image = $image;
            $item->CBM = $cbm;
            $item->CP = $cp;
            return $item;
        });

        // Supplier name → default zone (trimmed); used to auto-fill Zone when empty or on supplier change
        $supplierZoneMap = [];
        foreach (Supplier::where('type', 'Supplier')->whereNotNull('zone')->where('zone', '!=', '')->get(['name', 'zone']) as $sz) {
            $supplierZoneMap[trim((string) $sz->name)] = trim((string) $sz->zone);
        }

        $resolveZoneForSupplier = static function (string $supplierName, array $map): ?string {
            $s = trim($supplierName);
            if ($s === '') {
                return null;
            }
            if (isset($map[$s])) {
                return $map[$s];
            }
            foreach ($map as $n => $z) {
                if (strcasecmp(trim((string) $n), $s) === 0) {
                    return $z;
                }
            }

            return null;
        };

        foreach ($readyToShipData as $item) {
            $area = trim((string) ($item->area ?? ''));
            $sup = trim((string) ($item->supplier ?? ''));
            if ($area !== '' || $sup === '') {
                continue;
            }
            $z = $resolveZoneForSupplier($sup, $supplierZoneMap);
            if ($z !== null && $z !== '' && ! empty($item->id)) {
                ReadyToShip::whereKey($item->id)->update(['area' => $z]);
                $item->area = $z;
            }
        }

        // Transit container modal (same as transit-container-details)
        $transitTabs = TransitContainerDetail::where(function ($q) {
            $q->whereNull('status')->orWhereRaw("TRIM(status) = ''");
        })->distinct()->pluck('tab_name')->toArray();
        if (empty($transitTabs)) {
            $transitTabs = ['Container 1'];
        }
        $transitSuppliers = Supplier::select('id', 'name')->get();
        $transitSkus = ProductMaster::pluck('sku')->toArray();
        $transitProductValuesMap = [];
        foreach (ProductMaster::select('sku', 'Values')->get() as $p) {
            $normSku = strtoupper(trim(preg_replace('/\s+/', ' ', $p->sku ?? '')));
            $val = $p->Values;
            if (is_array($val)) {
                $transitProductValuesMap[$normSku] = $val;
            } elseif (is_string($val) && $val !== '') {
                $decoded = json_decode($val, true);
                $transitProductValuesMap[$normSku] = is_array($decoded) ? $decoded : [];
            } else {
                $transitProductValuesMap[$normSku] = [];
            }
        }

        return view('purchase-master.ready-to-ship.index', [
            'readyToShipList' => $readyToShipData,
            'suppliers' => Supplier::pluck('name'),
            'supplierZoneMap' => $supplierZoneMap,
            'transitTabs' => $transitTabs,
            'transitSuppliers' => $transitSuppliers,
            'transitSkus' => $transitSkus,
            'transitProductValuesMap' => json_encode($transitProductValuesMap, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Return current R2S total (same logic as Ready to Ship blade: sum of qty*CP for stage r2s, nr != NR).
     * Ready to Ship uses CP from product_master Values->cp, not rate. Used by Forecast Analysis etc.
     */
    public function r2sTotal(Request $request)
    {
        $normalizeSku = function ($sku) {
            if (empty($sku)) return '';
            $sku = strtoupper(trim($sku));
            $sku = preg_replace('/\s+/u', ' ', $sku);
            $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);
            return trim($sku);
        };

        $forecastData = DB::table('forecast_analysis')
            ->get()
            ->groupBy(function ($item) use ($normalizeSku) {
                return $normalizeSku($item->sku);
            })
            ->map(function ($group) {
                $withStage = $group->firstWhere('stage', '!=', null);
                if ($withStage && !empty(trim($withStage->stage ?? ''))) {
                    return $withStage;
                }
                return $group->first();
            });

        $productMaster = DB::table('product_master')->get()->keyBy(fn($row) => strtoupper(trim($row->sku ?? '')));

        $readyToShipRows = ReadyToShip::where('transit_inv_status', 0)->whereNull('deleted_at')->get();
        $total = 0;

        foreach ($readyToShipRows as $item) {
            $sku = $normalizeSku($item->sku);
            if (!$forecastData->has($sku)) {
                continue;
            }
            $forecast = $forecastData->get($sku);
            $stage = strtolower(trim($forecast->stage ?? ''));
            if ($stage !== 'r2s') {
                continue;
            }
            $nr = strtoupper(trim($forecast->nr ?? ''));
            if ($nr === 'NR') {
                continue;
            }

            $cp = null;
            $productRow = $productMaster[$sku] ?? null;
            if (!$productRow) {
                foreach ([$sku, str_replace(' ', '', $sku)] as $skuVar) {
                    if (isset($productMaster[$skuVar])) {
                        $productRow = $productMaster[$skuVar];
                        break;
                    }
                }
            }
            if (!$productRow && !empty($item->sku)) {
                $productRow = DB::table('product_master')
                    ->whereRaw('UPPER(TRIM(sku)) = ?', [$sku])
                    ->orWhereRaw('UPPER(TRIM(sku)) LIKE ?', ['%' . str_replace(' ', '%', $sku) . '%'])
                    ->first();
            }
            if ($productRow) {
                $values = json_decode($productRow->Values ?? '{}', true);
                if (is_array($values) && isset($values['cp'])) {
                    $cp = (float) $values['cp'];
                }
            }
            $qty = is_numeric($item->qty) ? (float) $item->qty : 0;
            if ($qty > 0 && $cp !== null && $cp > 0) {
                $total += $qty * $cp;
            }
        }

        return response()->json(['value' => round($total)]);
    }

    public function inlineUpdateBySku(Request $request)
    {
        $sku = $request->input('sku');
        $column = $request->input('column');
        $value = $request->input('value');
        $normalizedSku = trim((string) $sku);
        $item = ReadyToShip::query()
            ->whereRaw('TRIM(sku) = ?', [$normalizedSku])
            ->where('transit_inv_status', 0)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->first();
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'SKU not found in ready_to_ships']);
        }
        $qty = $item->qty;

        if($column === 'rec_qty'){
            $value = is_numeric($value) ? (int)$value : null;
            if($value !== null) {
                $item->qty = $qty - $value;
                $item->save();
            }
        }

        if (!in_array($column, [
            'rec_qty',
            'rate',
            'area',
            'pay_term',
            'payment_confirmation',
            'payment',
            'packing_list',
            'photo_mail_send',
            'supplier',
            'supplier_sku',
        ])) {
            return response()->json(['success' => false, 'message' => 'Invalid column.']);
        }

        if ($column === 'pay_term') {
            $value = strtoupper(trim((string) $value));
            if (!in_array($value, ['EXW', 'FOB'], true)) {
                $value = 'EXW';
            }
        }

        $item->$column = $value;
        $item->save();

        return response()->json(['success' => true]);
    }

    public function revertBackMfrg(Request $request)
    {
        $skus = $request->input('skus');

        if (!is_array($skus) || empty($skus)) {
            return response()->json(['success' => false, 'message' => 'No SKUs provided.']);
        }

        try {
            ReadyToShip::whereIn('sku', $skus)->delete();
            MfrgProgress::whereIn('sku', $skus)->update(['ready_to_ship' => 'No']);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Action failed: ' . $e->getMessage()]);
        }
    }

    public function moveToTransit(Request $request)
    {
        $tabName = '';
        $ids = [];
        $skus = [];
        $recQtyById = [];
        $recQtyBySku = [];

        // Always parse JSON from raw body when it looks like JSON (fixes missing ids when Content-Type is odd)
        $raw = (string) $request->getContent();
        if ($raw !== '' && str_starts_with(ltrim($raw), '{')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $tabName = trim((string) ($decoded['tab_name'] ?? ''));
                if (!empty($decoded['ids']) && is_array($decoded['ids'])) {
                    $ids = array_values(array_filter(array_map('intval', $decoded['ids']), fn ($id) => $id > 0));
                }
                if (!empty($decoded['skus']) && is_array($decoded['skus'])) {
                    $skus = array_values(array_filter(array_map('strval', $decoded['skus']), fn ($s) => $s !== ''));
                }
                if (!empty($decoded['rec_qty_by_id']) && is_array($decoded['rec_qty_by_id'])) {
                    foreach ($decoded['rec_qty_by_id'] as $k => $v) {
                        $id = (int) $k;
                        if ($id > 0 && is_numeric($v)) {
                            $recQtyById[$id] = max(0, min(100000, (float) $v));
                        }
                    }
                }
                if (!empty($decoded['rec_qty_by_sku']) && is_array($decoded['rec_qty_by_sku'])) {
                    foreach ($decoded['rec_qty_by_sku'] as $k => $v) {
                        $skuKey = trim((string) $k);
                        if ($skuKey !== '' && is_numeric($v)) {
                            $recQtyBySku[strtoupper(preg_replace('/\s+/u', ' ', $skuKey))] = max(0, min(100000, (float) $v));
                        }
                    }
                }
            }
        }

        if ($tabName === '') {
            $tabName = trim((string) $request->input('tab_name', ''));
        }
        if (empty($ids)) {
            $rawIds = $request->input('ids', []);
            if (!is_array($rawIds)) {
                $rawIds = $rawIds !== null && $rawIds !== '' ? [$rawIds] : [];
            }
            $ids = array_values(array_filter(array_map('intval', $rawIds), fn ($id) => $id > 0));
        }
        if (empty($skus)) {
            $rawSkus = $request->input('skus', []);
            if (!is_array($rawSkus)) {
                $rawSkus = $rawSkus ? [(string) $rawSkus] : [];
            }
            $skus = array_values(array_filter(array_map('strval', $rawSkus), fn ($s) => $s !== ''));
        }

        if ($tabName === '') {
            Log::warning('[ReadyToShip] moveToTransit rejected: empty tab_name', ['ids' => $ids, 'skus_count' => count($skus)]);

            return response()->json(['success' => false, 'message' => 'Please choose a container.']);
        }

        if (!empty($ids)) {
            $readyItems = ReadyToShip::whereIn('id', $ids)
                ->where('transit_inv_status', 0)
                ->whereNull('deleted_at')
                ->get();
        } elseif (!empty($skus)) {
            $readyItems = ReadyToShip::whereIn('sku', $skus)
                ->where('transit_inv_status', 0)
                ->whereNull('deleted_at')
                ->get();
        } else {
            Log::warning('[ReadyToShip] moveToTransit rejected: no ids or skus', ['parsed_ids' => $ids]);

            return response()->json(['success' => false, 'message' => 'No rows selected.']);
        }

        if ($readyItems->isEmpty()) {
            Log::warning('[ReadyToShip] moveToTransit: no matching rows', [
                'tab_name' => $tabName,
                'ids' => $ids,
                'skus' => $skus,
            ]);

            return response()->json(['success' => false, 'message' => 'No matching ready-to-ship rows to move.']);
        }

        $removedIds = [];
        $partialUpdates = [];

        try {
            DB::beginTransaction();

            $normalizeSku = function ($sku) {
                if (empty($sku)) {
                    return '';
                }
                $sku = strtoupper(trim($sku));
                $sku = preg_replace('/\s+/u', ' ', $sku);

                return trim($sku);
            };

            $productMaster = DB::table('product_master')
                ->get()
                ->keyBy(fn ($row) => $normalizeSku($row->sku ?? ''));

            foreach ($readyItems as $item) {
                $orderQty = (float) ($item->qty ?? 0);
                if ($orderQty <= 0) {
                    continue;
                }

                $recQtyInput = $recQtyById[$item->id] ?? null;
                if ($recQtyInput === null) {
                    $skuNorm = $normalizeSku($item->sku ?? '');
                    $recQtyInput = $skuNorm !== '' ? ($recQtyBySku[$skuNorm] ?? null) : null;
                }
                if ($recQtyInput === null) {
                    $recQtyInput = $item->rec_qty ?? $item->qty;
                }
                $recQtyInput = is_numeric($recQtyInput) ? (float) $recQtyInput : 0.0;
                $recQtyInput = max(0, min(100000, $recQtyInput));

                if ($recQtyInput <= 0) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Rec. Qty must be greater than 0 (SKU: ' . ($item->sku ?? '') . ').',
                    ], 422);
                }

                /** Rec < Or. Qty → partial move; Rec ≥ Or. Qty → move full order qty and remove R2S row */
                $isPartial = $recQtyInput < $orderQty;
                $qtyToTransit = $isPartial ? $recQtyInput : $orderQty;

                $rate = $item->rate ?? null;
                $cbm = $item->cbm ?? null;
                if ($cbm === null || $cbm === '') {
                    $skuNorm = $normalizeSku($item->sku);
                    if (isset($productMaster[$skuNorm])) {
                        $valuesRaw = $productMaster[$skuNorm]->Values ?? '{}';
                        $values = json_decode($valuesRaw, true);
                        if (is_array($values) && isset($values['cbm'])) {
                            $cbm = (float) $values['cbm'];
                        }
                    }
                } else {
                    $cbm = is_numeric($cbm) ? (float) $cbm : $cbm;
                }

                $existing = TransitContainerDetail::where('our_sku', $item->sku)->where('tab_name', $tabName)->first();
                if ($existing) {
                    $prevCtn = (float) ($existing->total_ctn ?? 0);
                    $newCtn = $prevCtn + $qtyToTransit;
                    $existing->update([
                        'total_ctn' => $newCtn,
                        'rec_qty' => $newCtn,
                        'rate' => $rate ?? $existing->rate,
                        'cbm' => $cbm ?? $existing->cbm,
                        'updated_at' => now(),
                    ]);
                } else {
                    TransitContainerDetail::create([
                        'our_sku' => $item->sku,
                        'tab_name' => $tabName,
                        'rec_qty' => $qtyToTransit,
                        'no_of_units' => 1,
                        'total_ctn' => $qtyToTransit,
                        'rate' => $rate,
                        'cbm' => $cbm,
                        'created_at' => now(),
                        'created_by' => auth()->id(),
                        'updated_at' => now(),
                    ]);
                }

                if ($isPartial) {
                    $newQty = $orderQty - $recQtyInput;
                    if ($newQty < 0) {
                        $newQty = 0;
                    }
                    $item->update([
                        'qty' => $newQty,
                        'rec_qty' => null,
                        'updated_at' => now(),
                    ]);
                    $partialUpdates[] = [
                        'id' => (int) $item->id,
                        'new_qty' => $newQty,
                        'sku' => (string) ($item->sku ?? ''),
                    ];
                } else {
                    $item->update([
                        'transit_inv_status' => 1,
                        'rec_qty' => null,
                        'updated_at' => now(),
                    ]);
                    $removedIds[] = (int) $item->id;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('moveToTransit failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'message' => 'Move failed: ' . $e->getMessage(),
            ], 500);
        }

        $nRemoved = count($removedIds);
        $nPartial = count($partialUpdates);
        $parts = [];
        if ($nRemoved) {
            $parts[] = $nRemoved . ' row(s) moved completely';
        }
        if ($nPartial) {
            $parts[] = $nPartial . ' row(s) updated (balance left on Ready to Ship)';
        }
        $msg = $parts ? implode('; ', $parts) . ' to "' . $tabName . '".' : 'No changes.';

        return response()->json([
            'success' => true,
            'message' => $msg . ' Open Transit Container Details to view.',
            'removed_ids' => $removedIds,
            'partial_updates' => $partialUpdates,
        ]);
    }

    public function deleteItems(Request $request)
    {
        try {
            $ids = $request->input('skus', []);

            if (!empty($ids)) {
                $user = auth()->check() ? auth()->user()->name : 'System';

                ReadyToShip::whereIn('sku', $ids)->update([
                    'auth_user' => $user,
                    'deleted_at' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Records soft-deleted successfully by ' . $user,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No IDs provided',
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting records: ' . $e->getMessage(),
            ], 500);
        }
    }


}
