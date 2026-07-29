<?php

namespace App\Http\Controllers;

use App\Models\ArrivedContainer;
use App\Models\ArrivedContainerHistory;
use App\Models\ComparisonData;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\Supplier;
use App\Models\MfrgProgress;
use App\Models\ReadyToShip;
use App\Models\Task;
use App\Models\TransitContainerDetail;
use App\Services\ArrivedContainerPoLookup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\OnSeaTransit;
use Carbon\Carbon;

class ArrivedContainerController extends Controller
{
    protected function logHistory(
        string $actionType,
        ?int $arrivedContainerId = null,
        ?string $fromTab = null,
        ?string $toTab = null,
        ?string $ourSku = null,
        $details = null
    ): void {
        ArrivedContainerHistory::create([
            'action_type' => $actionType,
            'arrived_container_id' => $arrivedContainerId,
            'from_tab' => $fromTab,
            'to_tab' => $toTab,
            'our_sku' => $ourSku,
            'details' => is_array($details) || is_object($details) ? json_encode($details) : $details,
            'user_id' => Auth::id(),
        ]);
    }

    public function index()
    {

        $allRecords = ArrivedContainer::with('user')->where(function ($q) {
            $q->whereNull('status')->orWhereRaw("TRIM(status) = ''");
        })->get();

        $tabs = ArrivedContainer::with('user')->where(function ($q) {
            $q->whereNull('status')->orWhereRaw("TRIM(status) = ''");
        })->distinct()->pluck('tab_name')->toArray();

        if (empty($tabs)) {
            $tabs = ['Container 1'];
        }

        usort($tabs, function ($a, $b) {
            preg_match('/(\d+)/', (string) $a, $mA);
            preg_match('/(\d+)/', (string) $b, $mB);

            return ((int) ($mB[1] ?? 0)) <=> ((int) ($mA[1] ?? 0));
        });

        $normalizeSku = static function ($value) {
            return strtoupper(trim(preg_replace('/\s+/', ' ', (string) $value)));
        };

        $skuParentMap = ProductMaster::pluck('parent', 'sku')
            ->mapWithKeys(function ($parent, $sku) use ($normalizeSku) {
                return [$normalizeSku($sku) => $normalizeSku($parent)];
            })->toArray();

        // Same supplier source as /forecast.analysis:
        // 1) to_order_analysis.supplier_name (when set, incl. empty string)
        // 2) else mfrg_progress.supplier
        $supplierRows = Supplier::where('type', 'Supplier')->get(['name', 'parent']);
        $parentSupplierMap = [];
        foreach ($supplierRows as $supplier) {
            $parentList = array_map('trim', explode(',', strtoupper($supplier->parent ?? '')));
            foreach ($parentList as $parent) {
                if ($parent === '') {
                    continue;
                }
                $parentSupplierMap[$parent][] = $supplier->name;
            }
        }

        $toOrderSupplierBySku = [];
        $toOrderRows = DB::table('to_order_analysis')
            ->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['sku', 'supplier_name']);
        foreach ($toOrderRows as $r) {
            $k = $normalizeSku($r->sku);
            if ($k === '' || array_key_exists($k, $toOrderSupplierBySku)) {
                continue;
            }
            if ($r->supplier_name !== null) {
                $toOrderSupplierBySku[$k] = (string) $r->supplier_name;
            }
        }

        $mfrgSupplierBySku = [];
        $mfrgRows = DB::table('mfrg_progress')
            ->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['sku', 'supplier']);
        foreach ($mfrgRows as $r) {
            $k = $normalizeSku($r->sku);
            if ($k === '' || array_key_exists($k, $mfrgSupplierBySku)) {
                continue;
            }
            $mfrgSupplierBySku[$k] = trim((string) ($r->supplier ?? ''));
        }

        $shopifyImages = ShopifySku::pluck('image_src', 'sku')->mapWithKeys(function ($value, $key) use ($normalizeSku) {
            return [$normalizeSku($key) => $value];
        })->toArray();

        $productValuesMap = ProductMaster::pluck('Values', 'sku')->mapWithKeys(function ($value, $key) use ($normalizeSku) {
            return [$normalizeSku($key) => $value];
        })->toArray();

        // Clink from forecast_analysis — same source as /transit-container-details.
        $clinkBySku = [];
        foreach (DB::table('forecast_analysis')->orderBy('id')->get(['sku', 'clink']) as $fr) {
            $k = $normalizeSku($fr->sku ?? '');
            if ($k !== '') {
                $clinkBySku[$k] = (string) ($fr->clink ?? '');
            }
        }

        $allRecords->transform(function ($record) use (
            $skuParentMap,
            $parentSupplierMap,
            $shopifyImages,
            $productValuesMap,
            $toOrderSupplierBySku,
            $mfrgSupplierBySku,
            $clinkBySku,
            $normalizeSku
        ) {
            $sku = $normalizeSku($record->our_sku ?? '');

            $parent = $skuParentMap[$sku] ?? null;

            if (empty($record->parent) && $parent) {
                $record->parent = $parent;
            }

            $parentKey = $normalizeSku($record->parent ?? '');
            $record->supplier_names = isset($parentSupplierMap[$parentKey])
                ? array_values(array_unique($parentSupplierMap[$parentKey]))
                : [];

            // Display supplier = same as Forecast Supplier column (mfrg_supplier)
            if (array_key_exists($sku, $toOrderSupplierBySku)) {
                $record->supplier_name = $toOrderSupplierBySku[$sku];
            } else {
                $record->supplier_name = $mfrgSupplierBySku[$sku] ?? '';
            }

            $record->image_src = $shopifyImages[$sku] ?? null;
            $values = $productValuesMap[$sku] ?? null;
            if (is_string($values)) {
                $decoded = json_decode($values, true);
                $values = is_array($decoded) ? $decoded : null;
            }
            $record->Values = $values;
            // Unit from CP Master (product_master.Values.unit) — same as /product-master datatable.
            $pmUnit = is_array($values) ? trim((string) ($values['unit'] ?? '')) : '';
            $record->unit = $pmUnit !== '' ? $pmUnit : null;
            $record->created_by_name = $record->user->name ?? '—';
            $record->setAttribute('Clink', $clinkBySku[$sku] ?? '');

            return $record;
        });

        // CD column: comparison_data sheet presence (same source as QC / Pricing / Forecast).
        $skuList = $allRecords
            ->pluck('our_sku')
            ->map(fn ($sku) => trim((string) $sku))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $sheetBySku = $skuList === []
            ? collect()
            : ComparisonData::query()
                ->whereIn('sku', $skuList)
                ->get(['sku', 'sheet_data'])
                ->keyBy(fn ($row) => strtoupper(trim((string) $row->sku)));

        $allRecords->transform(function ($record) use ($sheetBySku, $normalizeSku) {
            $skuKey = $normalizeSku($record->our_sku ?? '');
            $sheetRow = $skuKey !== '' ? $sheetBySku->get($skuKey) : null;
            $cells = is_array($sheetRow?->sheet_data['cells'] ?? null) ? $sheetRow->sheet_data['cells'] : [];
            $hasSheet = false;
            foreach ($cells as $row) {
                if (! is_array($row)) {
                    continue;
                }
                foreach ($row as $value) {
                    if (trim((string) $value) !== '') {
                        $hasSheet = true;
                        break 2;
                    }
                }
            }
            $record->has_sheet_data = $hasSheet;

            return $record;
        });

        [$poBySku, $allPoOptions] = ArrivedContainerPoLookup::build();
        ArrivedContainerPoLookup::attachPoOptions($allRecords, $poBySku);

        $groupedData = $allRecords->groupBy('tab_name');
        foreach ($tabs as $tab) {
            if (!isset($groupedData[$tab])) {
                $groupedData[$tab] = collect([]);
            }
        }

        $groupedData = collect($tabs)->mapWithKeys(fn ($tab) => [$tab => $groupedData[$tab]]);

        return view('purchase-master.transit_container.arrived-conatiner', [
            'tabs' => $tabs,
            'groupedData' => $groupedData,
            'allPoOptions' => $allPoOptions,
        ]);
    }

    public function pushArrivedContainer(Request $request)
    {
        $tabName = $request->input('tab_name');
        $rows = $request->input('data', []);

        $userId = auth()->id();

        foreach ($rows as $row) {
            $transitId = $row['id'] ?? null;
            $model = ArrivedContainer::updateOrCreate(
                [
                    'transit_container_id' => $transitId,
                    'tab_name'          => $row['tab_name'] ?? $tabName,
                ],
                [
                    'tab_name'          => $row['tab_name'] ?? null,
                    'our_sku'          => $row['our_sku'] ?? null,
                    'supplier_name'    => $row['supplier_name'] ?? null,
                    'company_name'     => $row['company_name'] ?? null,
                    'parent'           => $row['parent'] ?? null,
                    'no_of_units'      => !empty($row['no_of_units']) ? (int) $row['no_of_units'] : null,
                    'total_ctn'       => !empty($row['total_ctn']) ? (int) $row['total_ctn'] : null,
                    'rate'              => !empty($row['rate']) ? (float) $row['rate'] : null,
                    'unit'              => $row['unit'] ?? null,
                    'changes'           => $row['changes'] ?? null,
                    'package_size'      => $row['package_size'] ?? null,
                    'product_size_link' => $row['product_size_link'] ?? null,
                    'comparison_link'   => $row['comparison_link'] ?? null,
                    'order_link'        => $row['order_link'] ?? null,
                    'image_src'         => $row['image_src'] ?? null,
                    'photos'            => $row['photos'] ?? null,
                    'specification'     => $row['specification'] ?? null,
                    'created_by'        => $userId,
                ]
            );

            $this->logHistory('pushed_from_transit', $model->id, null, $model->tab_name, $model->our_sku, [
                'transit_container_id' => $transitId,
                'tab_name' => $model->tab_name,
                'sku' => $model->our_sku,
            ]);

            if (!empty($row['id'])) {
                TransitContainerDetail::where('id', $row['id'])->update([
                    'status' => 'inactive',
                ]);
            }

            // End-of-cycle handling for the SKU we just Arrived.
            //
            // User intent ("the cycle ends here, save in archived"):
            //   1. The SKU must no longer appear on the MIP In Progress page.
            //   2. Its data should remain available via the MIP page's "Show archived"
            //      toggle (i.e. soft-deleted, restorable — same behavior as the existing
            //      Archive button on MIP), not hard-deleted.
            //   3. If a stage is set on the row in forecast_analysis we clear it back to
            //      "Select stage" (empty), so the forecast page applies its own rule
            //      format from there instead of leaving a misleading stage like 'mip' /
            //      'transit' / 'r2s' hanging around. We don't put 'all_good' there — that
            //      would just be another active stage instead of the empty "Select" state.
            $rawSku = (string) ($row['our_sku'] ?? '');
            $normSku = strtoupper(trim(preg_replace('/\s+/', ' ', $rawSku)));

            if ($normSku !== '') {
                $rawParent = (string) ($row['parent'] ?? '');
                $normParent = strtoupper(trim(preg_replace('/\s+/', ' ', $rawParent)));

                // (1+2) Archive any matching mfrg_progress row(s) — soft delete via the
                // SoftDeletes trait. Mirrors what /mfrg-progresses/delete already does
                // when a user clicks the MIP "Archive" button, so the row shows up under
                // the same "Show archived" filter and can be restored from there.
                MfrgProgress::query()
                    ->whereRaw('TRIM(UPPER(sku)) = ?', [$normSku])
                    ->delete();

                // Same for ready_to_ship rows still on R2S (transit_inv_status = 0) — if
                // the SKU was an RTS row showing on MIP it must also drop off.
                ReadyToShip::query()
                    ->whereRaw('TRIM(UPPER(sku)) = ?', [$normSku])
                    ->where('transit_inv_status', 0)
                    ->delete();

                // (3) Clear the stage in forecast_analysis so it goes back to the
                // "Select stage" / empty state and the forecast page applies its rules.
                // Parent-scoped match first so SKUs reused across parents don't bleed.
                $cleared = 0;
                if ($normParent !== '') {
                    $cleared = (int) DB::table('forecast_analysis')
                        ->whereRaw('TRIM(UPPER(sku)) = ?', [$normSku])
                        ->whereRaw('TRIM(UPPER(COALESCE(parent, ?))) = ?', ['', $normParent])
                        ->update(['stage' => '', 'updated_at' => now()]);
                }
                if ($cleared === 0) {
                    DB::table('forecast_analysis')
                        ->whereRaw('TRIM(UPPER(sku)) = ?', [$normSku])
                        ->update(['stage' => '', 'updated_at' => now()]);
                }
                // No forecast row? Do NOT insert one — the user said "if stage is
                // required" it should go to Select; if there isn't one, no stage is
                // required and we shouldn't fabricate a row.
            }
        }

        $pricingTask = $this->createVerifyPricingContainerTask($tabName, count($rows));
        $invVerifyTask = $this->createInvVerifyContainerTask($tabName, count($rows));
        $qcTask = $this->createQcContainerTask($tabName, count($rows));

        return response()->json([
            'success' => true,
            'message' => 'Inventory pushed successfully',
            'count'   => count($rows),
            'task'    => $pricingTask,
            'inv_verify_task' => $invVerifyTask,
            'qc_task' => $qcTask,
        ]);
    }

    /**
     * Extract container number from tab labels like "Container 12" / "C 12".
     */
    private function containerNumberFromTabName(?string $tabName): string
    {
        if (preg_match('/(\d+)/', (string) $tabName, $m)) {
            return $m[1];
        }

        return '';
    }

    /**
     * Shared one-time Task Manager create after Push to Arrived.
     *
     * @return array{created:bool,id:?int,title:string,message:string}
     */
    private function createPushArrivedTask(string $title, string $link, ?string $tabName, int $rowCount, string $purpose): array
    {
        $assignorEmail = 'president@5core.com';
        $assigneeEmail = 'inventory@5core.com';

        try {
            $startDate = now();
            $completionDate = Carbon::parse($startDate)->addDays(5);
            $containerLabel = trim((string) $tabName);
            $description = 'Auto-created when container was Push to Arrived.'
                .($containerLabel !== '' ? ' Container: '.$containerLabel.'.' : '')
                .($rowCount > 0 ? ' Items pushed: '.$rowCount.'.' : '')
                .' '.$purpose;

            $task = Task::create([
                'title' => $title,
                'description' => $description,
                'group' => 'Purchase',
                'priority' => 'high',
                'assignor' => $assignorEmail,
                'assign_to' => $assigneeEmail,
                'status' => 'Todo',
                'eta_time' => 30,
                'start_date' => $startDate,
                'completion_date' => $completionDate,
                'due_date' => $completionDate,
                'completion_day' => 0,
                'etc_done' => 0,
                'is_missed' => 0,
                'is_missed_track' => 0,
                'workspace' => 0,
                'order' => 0,
                'task_id' => '',
                'link1' => $link,
                'link2' => '',
                'link3' => '',
                'link4' => '',
                'link5' => '',
                'link6' => '',
                'link7' => '',
                'link8' => '',
                'link9' => '',
                'is_data_from' => 0,
                'is_automate_task' => 0,
                'task_type' => 'manual',
                'rework_reason' => '',
                'delete_rating' => 0,
                'delete_feedback' => '',
                'split_tasks' => 0,
            ]);

            return [
                'created' => true,
                'id' => (int) $task->id,
                'title' => $title,
                'message' => 'Task "'.$title.'" created for Ritu.',
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to create Push to Arrived task "'.$title.'": '.$e->getMessage());

            return [
                'created' => false,
                'id' => null,
                'title' => $title,
                'message' => 'Arrived push succeeded but task "'.$title.'" creation failed.',
            ];
        }
    }

    /**
     * One-time Task Manager task after Push to Arrived:
     * title "Verify pricing Container", L1 = /pricing/container.
     *
     * @return array{created:bool,id:?int,title:string,message:string}
     */
    private function createVerifyPricingContainerTask(?string $tabName, int $rowCount): array
    {
        return $this->createPushArrivedTask(
            'Verify pricing Container',
            '/pricing/container',
            $tabName,
            $rowCount,
            'Open Pricing Container to verify pricing.'
        );
    }

    /**
     * One-time Task Manager task after Push to Arrived:
     * title "inv Verify Container {N}", L1 = /inv-verify/container.
     *
     * @return array{created:bool,id:?int,title:string,message:string}
     */
    private function createInvVerifyContainerTask(?string $tabName, int $rowCount): array
    {
        $containerNo = $this->containerNumberFromTabName($tabName);
        $title = 'inv Verify Container'.($containerNo !== '' ? ' '.$containerNo : '');

        return $this->createPushArrivedTask(
            $title,
            '/inv-verify/container',
            $tabName,
            $rowCount,
            'Open Inv Verify Container to verify carton quantities.'
        );
    }

    /**
     * One-time Task Manager task after Push to Arrived:
     * title "QC Container {N}", L1 = /qc/container.
     *
     * @return array{created:bool,id:?int,title:string,message:string}
     */
    private function createQcContainerTask(?string $tabName, int $rowCount): array
    {
        $containerNo = $this->containerNumberFromTabName($tabName);
        $title = 'QC Container'.($containerNo !== '' ? ' '.$containerNo : '');

        return $this->createPushArrivedTask(
            $title,
            '/qc/container',
            $tabName,
            $rowCount,
            'Open QC Container to complete specs audit.'
        );
    }

    public function containerSummary(Request $request)
    {
        $containers = ArrivedContainer::where(function ($q) {
            $q->whereNull('status')->orWhereRaw("TRIM(status) = ''");
        })->get();
        //  OnSeaTransit::all();
        return view('purchase-master.transit_container.container-summary', ['onSeaTransitData' => [], 'chinaLoadMap' => []]);
    }

    /**
     * Save / update a row in Arrived Container (Tabulator cell edits).
     */
    public function saveArrivedRow(Request $request)
    {
        $data = $request->all();
        $tabName = $data['tab_name'] ?? null;
        if (empty($tabName)) {
            return response()->json(['success' => false, 'message' => 'Tab name is missing.'], 422);
        }

        $payload = [
            'tab_name' => $tabName,
            'our_sku' => $data['our_sku'] ?? null,
            'supplier_name' => $data['supplier_name'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'parent' => $data['parent'] ?? null,
            'no_of_units' => isset($data['no_of_units']) && $data['no_of_units'] !== '' ? (int) $data['no_of_units'] : null,
            'total_ctn' => isset($data['total_ctn']) && $data['total_ctn'] !== '' ? (int) $data['total_ctn'] : null,
            'rate' => isset($data['rate']) && $data['rate'] !== '' ? (float) $data['rate'] : null,
            'unit' => $data['unit'] ?? null,
            'changes' => $data['changes'] ?? null,
            'package_size' => $data['package_size'] ?? null,
            'product_size_link' => $data['product_size_link'] ?? null,
            'comparison_link' => $data['comparison_link'] ?? null,
            'order_link' => $data['order_link'] ?? null,
            'image_src' => $data['image_src'] ?? null,
            'photos' => $data['photos'] ?? null,
            'specification' => $data['specification'] ?? null,
        ];

        if (!empty($data['transit_container_id'])) {
            $payload['transit_container_id'] = (int) $data['transit_container_id'];
        }

        if (!empty($data['id'])) {
            $row = ArrivedContainer::find($data['id']);
            if (!$row) {
                return response()->json(['success' => false, 'message' => 'Row not found.'], 404);
            }
            $fromTab = $row->tab_name;
            $toTab = $tabName;
            $fieldDiff = [];
            foreach ($payload as $key => $newVal) {
                if ($key === 'tab_name') {
                    continue;
                }
                $oldVal = $row->getAttribute($key);
                $oldNorm = $oldVal === null ? '' : (string) $oldVal;
                $newNorm = $newVal === null ? '' : (string) $newVal;
                if ($oldNorm !== $newNorm) {
                    $fieldDiff[$key] = ['from' => $oldVal, 'to' => $newVal];
                }
            }
            $row->fill($payload);
            $row->save();

            if ($fromTab !== $toTab) {
                $this->logHistory('row_moved', $row->id, $fromTab, $toTab, $row->our_sku, [
                    'sku' => $row->our_sku,
                    'from' => $fromTab,
                    'to' => $toTab,
                ]);
            }
            if (!empty($fieldDiff)) {
                $this->logHistory('row_updated', $row->id, null, $toTab, $row->our_sku, $fieldDiff);
            }
        } else {
            $payload['created_by'] = Auth::id();
            $row = ArrivedContainer::create($payload);
            $this->logHistory('row_created', $row->id, null, $row->tab_name, $row->our_sku, null);
        }

        return response()->json(['success' => true, 'id' => $row->id]);
    }

    /**
     * History for Arrived Container (same filters as transit container history).
     */
    public function getHistory(Request $request)
    {
        $query = ArrivedContainerHistory::with('user')
            ->orderByDesc('created_at');

        if ($request->filled('tab_name')) {
            $tab = trim($request->tab_name);
            $query->where(function ($q) use ($tab) {
                $q->where('to_tab', $tab)->orWhere('from_tab', $tab);
            });
        }
        if ($request->filled('sku')) {
            $query->where('our_sku', 'like', '%' . trim($request->sku) . '%');
        }
        if ($request->filled('action_type')) {
            $query->where('action_type', $request->action_type);
        }

        $limit = min((int) $request->get('limit', 100), 500);
        $items = $query->limit($limit)->get()->map(function ($h) {
            return [
                'id' => $h->id,
                'action_type' => $h->action_type,
                'from_tab' => $h->from_tab,
                'to_tab' => $h->to_tab,
                'our_sku' => $h->our_sku,
                'details' => $h->details,
                'user_name' => $h->user->name ?? '—',
                'created_at' => $h->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json(['data' => $items]);
    }
}