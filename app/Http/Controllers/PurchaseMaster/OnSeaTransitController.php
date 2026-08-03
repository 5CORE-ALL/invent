<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\ChinaLoad;
use App\Models\OnSeaTransit;
use App\Models\OnSeaTransitDetailsHistory;
use App\Models\TransitContainerDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OnSeaTransitController extends Controller
{
    /**
     * Transit Container Inv total for a tab (same formula as autoSyncToOnSea).
     */
    protected function transitContainerInvForTab(Collection $rows): float
    {
        $totalAmount = 0.0;
        foreach ($rows as $row) {
            $rate = (float) ($row->rate ?? 0);
            $noOfUnits = (float) ($row->no_of_units ?? 0);
            $totalCtn = (float) ($row->total_ctn ?? 0);
            $pcsQty = (float) ($row->pcs_qty ?? 0);
            $quantity = ($pcsQty > 0) ? $pcsQty : ($totalCtn * $noOfUnits);
            $totalAmount += $rate * $quantity;
        }

        return round($totalAmount);
    }

    /**
     * Map container_sl_no => Transit Container Inv total.
     */
    protected function transitInvByContainerSl(iterable $containerSlNos): array
    {
        $slNos = collect($containerSlNos)->filter()->unique()->values();
        if ($slNos->isEmpty()) {
            return [];
        }

        $tabNames = $slNos->map(fn ($sl) => 'Container ' . $sl)->all();
        $grouped = TransitContainerDetail::whereIn('tab_name', $tabNames)
            ->where(function ($q) {
                $q->whereNull('status')->orWhereRaw("TRIM(status) = ''");
            })
            ->get()
            ->groupBy('tab_name');

        $map = [];
        foreach ($grouped as $tabName => $rows) {
            if (preg_match('/Container\s+(\d+)/i', (string) $tabName, $matches)) {
                $map[$matches[1]] = $this->transitContainerInvForTab($rows);
            }
        }

        return $map;
    }

    public function index()
    {
        $chinaLoads = ChinaLoad::get(['container_sl_no', 'mbl', 'obl', 'container_no', 'item']);

        foreach ($chinaLoads as $load) {
            $exists = OnSeaTransit::where('container_sl_no', $load->container_sl_no)->exists();
            if (!$exists) {
                OnSeaTransit::create([
                    'container_sl_no' => $load->container_sl_no
                ]);
            }
        }

        // Earliest ETA Ohio first; rows with no date sink to the bottom.
        $records = OnSeaTransit::whereNull('archived_at')
            ->orderByRaw('eta_date_ohio IS NULL')
            ->orderBy('eta_date_ohio', 'asc')
            ->get();

        $transitInvBySl = $this->transitInvByContainerSl($records->pluck('container_sl_no'));

        $onSeaTransitData = $records->map(function ($item) use ($chinaLoads, $transitInvBySl) {
            $chinaLoad = $chinaLoads->firstWhere('container_sl_no', $item->container_sl_no);
            $slKey = (string) $item->container_sl_no;
            // Prefer live Transit Container Inv; fall back to stored invoice_value
            $invoiceValue = $transitInvBySl[$slKey] ?? $item->invoice_value ?? 0;
            $paid = $item->paid ?? 0;
            $freight = $item->freight ?? 0;
            $balance = ($invoiceValue + $freight) - $paid;
            
            return [
                'id' => $item->id,
                'container_sl_no' => $item->container_sl_no,
                'mbl' => $chinaLoad->mbl ?? null,
                'obl' => $chinaLoad->obl ?? null,
                'container_no' => $chinaLoad->container_no ?? null,
                'item' => $chinaLoad->item ?? null,
                'bl_check' => $item->bl_check,
                'bl_link' => $item->bl_link,
                'isf' => $item->isf,
                'etd' => $item->etd,
                'eta_port' => $item->eta_port,
                'port_arrival' => $item->port_arrival,
                'eta_date_ohio' => $item->eta_date_ohio,
                'duty_calcu' => $item->duty_calcu,
                'invoice_send_to_dominic' => $item->invoice_send_to_dominic,
                'arrival_notice_email' => $item->arrival_notice_email,
                'remarks' => $item->remarks,
                'status' => $item->status,
                'invoice_value' => $invoiceValue,
                'transit_inv_value' => $invoiceValue,
                'freight' => $item->freight,
                'agent' => $item->agent,
                'paid' => $item->paid,
                'balance' => $balance,
                'supplier_payments' => $item->supplier_payments ?? [],
                'details' => $item->details,
            ];
        });
        
        // All count/sum aggregates must ignore archived rows so the badges
        // stay in sync with the visible table (whereNull('archived_at')).
        $activeBase = fn () => OnSeaTransit::whereNull('archived_at');

        $totalCount = $activeBase()->count();
        $arrivedCount = $activeBase()->where('status', 'Arrived')->count();
        $planningCount = $activeBase()->where('status', 'Planning')->count();
        $remainingCount = $totalCount - ($arrivedCount + $planningCount);

        // Calculate total invoice value for filtered containers (all except Arrived and Planning)
        $totalInvoiceValue = $activeBase()->where(function ($query) {
            $query->whereNull('status')
                  ->orWhereNotIn('status', ['Arrived', 'Planning']);
        })->sum('invoice_value');

        // Calculate total pending amount (balance) for filtered containers
        $totalPendingAmount = $activeBase()->where(function ($query) {
            $query->whereNull('status')
                  ->orWhereNotIn('status', ['Arrived', 'Planning']);
        })->sum('balance');

        // "Value" badge — sum of the table's Value column (invoice_value) for
        // every row the user actually sees. The Tabulator front-end filters
        // out only 'Arrived' rows (see updateBadgeCounts), so we mirror that
        // here for the initial paint; the JS recomputes after any inline edit.
        $totalColumnValue = $activeBase()->where(function ($query) {
            $query->whereNull('status')
                  ->orWhere('status', '!=', 'Arrived');
        })->sum('invoice_value');

        $chinaLoadMap = $chinaLoads->keyBy('container_sl_no')->map(function ($load) {
            return [
                'mbl' => $load->mbl,
                'obl' => $load->obl,
                'container_no' => $load->container_no,
                'item' => $load->item,
            ];
        });

        return view('purchase-master.on_sea_transit.index', [
            'onSeaTransitData' => $onSeaTransitData,
            'chinaLoadMap' => $chinaLoadMap,
            'totalCount' => $totalCount,
            'arrivedCount' => $arrivedCount,
            'planningCount' => $planningCount,
            'remainingCount' => $remainingCount,
            'totalInvoiceValue' => $totalInvoiceValue,
            'totalPendingAmount' => $totalPendingAmount,
            'totalColumnValue' => $totalColumnValue,
        ]);
    }


    public function inlineUpdateOrCreate(Request $request)
    {
        $data = $request->only(['container_sl_no', 'column', 'value']);

        if (!$data['container_sl_no'] || !$data['column']) {
            return response()->json(['success' => false, 'message' => 'Missing data']);
        }

        $record = OnSeaTransit::firstOrNew(['container_sl_no' => $data['container_sl_no']]);
        $column = $data['column'];
        $oldValue = $record->exists ? $record->{$column} : null;
        $newValue = $data['value'];

        $record->{$column} = $newValue;

        // Auto-calculate balance when invoice_value or paid changes
        if ($column === 'invoice_value' || $column === 'paid') {
            $invoiceValue = $column === 'invoice_value' ? $newValue : $record->invoice_value;
            $paid = $column === 'paid' ? $newValue : $record->paid;
            $record->balance = ($invoiceValue ?? 0) - ($paid ?? 0);
        }

        $record->save();
        $this->logFieldChange($record, $column, $oldValue, $record->{$column});

        return response()->json([
            'success' => true,
            'balance' => $record->balance
        ]);
    }

    public function getDetailsHistory($id)
    {
        $history = OnSeaTransitDetailsHistory::where('on_sea_transit_id', $id)
            ->orderBy('changed_at', 'desc')
            ->get()
            ->map(function (OnSeaTransitDetailsHistory $item) {
                $changedAt = $item->changed_at;
                $fullName = trim((string) ($item->user_name ?? ''));
                $firstName = $fullName === ''
                    ? 'Unknown'
                    : (explode(' ', $fullName)[0] ?: 'Unknown');

                return [
                    'id' => $item->id,
                    'field' => $item->field ?: 'details',
                    'field_label' => $this->historyFieldLabel($item->field ?: 'details'),
                    'user_name' => $firstName,
                    'old_value' => $item->old_value,
                    'new_value' => $item->new_value,
                    'changed_at' => $changedAt?->toIso8601String(),
                    'date_label' => $changedAt
                        ? ($changedAt->format('j') . strtoupper($changedAt->format('M')))
                        : '',
                ];
            });

        return response()->json([
            'success' => true,
            'history' => $history
        ]);
    }

    /**
     * First name of the authenticated user (word before first space).
     */
    protected function authFirstName(): string
    {
        if (!auth()->check()) {
            return 'Unknown';
        }

        $name = trim((string) (auth()->user()->name ?? ''));
        if ($name === '') {
            return 'Unknown';
        }

        return explode(' ', $name)[0] ?: 'Unknown';
    }

    /**
     * Normalize a value for history comparison / storage.
     */
    protected function historyValueToString($value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }
        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->format('Y-m-d');
        }
        if (is_numeric($value) && !is_string($value)) {
            return (string) (0 + $value);
        }

        return trim((string) $value);
    }

    protected function historyFieldLabel(string $field): string
    {
        $labels = [
            'bl_check' => 'BL',
            'bl_link' => 'BL link',
            'isf' => 'ISF',
            'isf_usa_agent' => 'ISF (USA agent)',
            'etd' => 'ETD',
            'eta_port' => 'ETA Port',
            'port_arrival' => 'Port Arr',
            'eta_date_ohio' => 'ETA Ohio',
            'duty_calcu' => 'Duty',
            'invoice_send_to_dominic' => 'Invoice -> Dominic',
            'arrival_notice_email' => 'Arrival Notice',
            'remarks' => 'Remarks',
            'invoice_value' => 'Value',
            'freight' => 'Freight',
            'agent' => 'Agent',
            'paid' => 'Paid',
            'details' => 'Details',
            'status' => 'Status',
            'supplier_payments' => 'Supplier payments',
            'balance' => 'Due',
            'mbl' => 'MBL',
            'obl' => 'OBL',
            'container_no' => 'Container No',
            'item' => 'Item',
        ];

        return $labels[$field] ?? $field;
    }

    /**
     * Persist one history row when a field actually changed.
     */
    protected function logFieldChange(OnSeaTransit $record, string $field, $oldValue, $newValue): void
    {
        $oldStr = $this->historyValueToString($oldValue);
        $newStr = $this->historyValueToString($newValue);
        if ($oldStr === $newStr) {
            return;
        }
        if (!$record->id) {
            return;
        }

        OnSeaTransitDetailsHistory::create([
            'on_sea_transit_id' => $record->id,
            'container_sl_no' => $record->container_sl_no,
            'field' => $field,
            'user_name' => $this->authFirstName(),
            'old_value' => $oldStr === '' ? null : $oldStr,
            'new_value' => $newStr === '' ? null : $newStr,
            'changed_at' => now(),
        ]);
    }
    
    public function syncValue(Request $request)
    {
        $data = $request->only(['container_sl_no', 'invoice_value']);

        if (!$data['container_sl_no']) {
            return response()->json(['success' => false, 'message' => 'Container number is required']);
        }

        $record = OnSeaTransit::firstOrNew(['container_sl_no' => $data['container_sl_no']]);
        $record->invoice_value = $data['invoice_value'] ?? 0;
        
        // Auto-calculate balance
        $invoiceValue = $record->invoice_value ?? 0;
        $paid = $record->paid ?? 0;
        $record->balance = $invoiceValue - $paid;
        
        $record->save();

        return response()->json([
            'success' => true,
            'message' => 'Value synced successfully'
        ]);
    }

    /**
     * Archive a row from the On Sea Transit board.  Sets `archived_at = now()`
     * so the row stops appearing in the main view but stays in the DB.
     * Looked up by `id` (preferred) with a `container_sl_no` fallback.
     */
    public function archive(Request $request)
    {
        $id = $request->input('id');
        $containerSlNo = $request->input('container_sl_no');

        $record = $id
            ? OnSeaTransit::find($id)
            : ($containerSlNo ? OnSeaTransit::where('container_sl_no', $containerSlNo)->first() : null);

        if (!$record) {
            return response()->json(['success' => false, 'message' => 'Row not found'], 404);
        }

        $record->archived_at = now();
        $record->save();

        return response()->json(['success' => true]);
    }

    /**
     * Restore a previously archived row.  Symmetric to archive() — clears
     * archived_at so the row reappears on the board.
     */
    public function restore(Request $request)
    {
        $id = $request->input('id');
        $containerSlNo = $request->input('container_sl_no');

        $record = $id
            ? OnSeaTransit::find($id)
            : ($containerSlNo ? OnSeaTransit::where('container_sl_no', $containerSlNo)->first() : null);

        if (!$record) {
            return response()->json(['success' => false, 'message' => 'Row not found'], 404);
        }

        $record->archived_at = null;
        $record->save();

        return response()->json(['success' => true]);
    }

    /**
     * Bulk-update every editable field on a single row in one DB round-trip.
     * Powers the "Edit" pencil button in the Action column, which lets the
     * user fix every column at once instead of clicking each cell.
     *
     * Only fields present in the request body are touched, so partial edits
     * (e.g. just changing remarks + freight) leave the rest intact. Balance
     * is recomputed from invoice_value/paid the same way inlineUpdateOrCreate
     * does it, keeping the Due column honest.
     */
    public function updateRow(Request $request)
    {
        $containerSlNo = $request->input('container_sl_no');
        if (!$containerSlNo) {
            return response()->json(['success' => false, 'message' => 'container_sl_no is required'], 422);
        }

        $record = OnSeaTransit::firstOrNew(['container_sl_no' => $containerSlNo]);

        $editable = [
            'bl_check', 'bl_link', 'isf', 'isf_usa_agent', 'etd', 'eta_port',
            'port_arrival', 'eta_date_ohio', 'duty_calcu', 'invoice_send_to_dominic',
            'arrival_notice_email', 'remarks', 'invoice_value', 'freight', 'agent', 'paid',
            'details', 'status',
        ];

        // Snapshot before mutation so we can write change history.
        $trackFields = array_merge($editable, ['supplier_payments', 'balance']);
        $oldSnapshot = [];
        foreach ($trackFields as $field) {
            $oldSnapshot[$field] = $record->exists ? $record->{$field} : null;
        }

        foreach ($editable as $field) {
            if ($request->has($field)) {
                $value = $request->input($field);
                // Coerce empty strings to null so DECIMAL/DATE columns stay clean.
                $record->{$field} = ($value === '' ? null : $value);
            }
        }

        // Payment lines (supplier + agent) drive Value / Paid / Due when provided.
        if ($request->has('supplier_payments')) {
            $payload = $request->input('supplier_payments');
            $supplierLines = [];
            $agentLines = [];

            // New shape: { supplier: [...], agent: [...] }
            // Legacy: flat array of lines (treated as agent/supplier by keys)
            if (is_array($payload) && (isset($payload['supplier']) || isset($payload['agent']))) {
                $supplierLines = is_array($payload['supplier'] ?? null) ? $payload['supplier'] : [];
                $agentLines = is_array($payload['agent'] ?? null) ? $payload['agent'] : [];
            } elseif (is_array($payload)) {
                foreach ($payload as $line) {
                    if (!is_array($line)) {
                        continue;
                    }
                    if (!empty($line['agent']) || (($line['category'] ?? '') === 'agent')) {
                        $agentLines[] = $line;
                    } else {
                        $supplierLines[] = $line;
                    }
                }
            }

            $normalizedSupplier = [];
            $normalizedAgent = [];
            $sumAmount = 0.0;
            $sumPaid = 0.0;

            foreach ($supplierLines as $line) {
                if (!is_array($line)) {
                    continue;
                }
                $name = trim((string) ($line['name'] ?? $line['supplier_name'] ?? ''));
                $amount = round((float) ($line['amount'] ?? 0), 2);
                $paidLine = round((float) ($line['paid'] ?? 0), 2);
                if ($name === '' && $amount == 0.0 && $paidLine == 0.0) {
                    continue;
                }
                $lineBalance = round($amount - $paidLine, 2);
                $normalizedSupplier[] = [
                    'name' => $name,
                    'amount' => $amount,
                    'paid' => $paidLine,
                    'balance' => $lineBalance,
                ];
                $sumAmount += $amount;
                $sumPaid += $paidLine;
            }

            foreach ($agentLines as $line) {
                if (!is_array($line)) {
                    continue;
                }
                $agent = trim((string) ($line['agent'] ?? $line['name'] ?? ''));
                $amount = round((float) ($line['amount'] ?? 0), 2);
                $paidLine = round((float) ($line['paid'] ?? 0), 2);
                if ($agent === '' && $amount == 0.0 && $paidLine == 0.0) {
                    continue;
                }
                $lineBalance = round($amount - $paidLine, 2);
                $normalizedAgent[] = [
                    'agent' => $agent,
                    'amount' => $amount,
                    'paid' => $paidLine,
                    'balance' => $lineBalance,
                ];
                $sumAmount += $amount;
                $sumPaid += $paidLine;
            }

            $record->supplier_payments = [
                'supplier' => $normalizedSupplier,
                'agent' => $normalizedAgent,
            ];
            // Paid from payment lines; Value stays Transit Container Inv (not overwritten here)
            $record->paid = $sumPaid;
            // Freight is a single row value (not per line)
            if ($request->has('freight')) {
                $record->freight = round((float) $request->input('freight', 0), 2);
            }
        }

        // Keep Value aligned with Transit Container Inv when available
        $transitInv = $this->transitInvByContainerSl([$containerSlNo]);
        $slKey = (string) $containerSlNo;
        if (array_key_exists($slKey, $transitInv)) {
            $record->invoice_value = $transitInv[$slKey];
        }

        // Due = Transit Inv Value + Freight − Paid
        $invoiceValue = $record->invoice_value ?? 0;
        $freight = $record->freight ?? 0;
        $paid = $record->paid ?? 0;
        $record->balance = ($invoiceValue + $freight) - $paid;

        $record->save();

        // Log every field that actually changed from this Edit modal save.
        foreach ($trackFields as $field) {
            $this->logFieldChange($record, $field, $oldSnapshot[$field] ?? null, $record->{$field});
        }

        // China Load fields (MBL / OBL / Container No / Item) — editable from this modal.
        $chinaFields = ['mbl', 'obl', 'container_no', 'item'];
        $chinaLoad = ChinaLoad::firstOrNew(['container_sl_no' => $containerSlNo]);
        $chinaChanged = false;
        foreach ($chinaFields as $field) {
            if (!$request->has($field)) {
                continue;
            }
            $oldChina = $chinaLoad->exists ? $chinaLoad->{$field} : null;
            $newChina = $request->input($field);
            $newChina = ($newChina === '' ? null : $newChina);
            $chinaLoad->{$field} = $newChina;
            $this->logFieldChange($record, $field, $oldChina, $newChina);
            $chinaChanged = true;
        }
        if ($chinaChanged) {
            if (!$chinaLoad->container_sl_no) {
                $chinaLoad->container_sl_no = $containerSlNo;
            }
            $chinaLoad->save();
        }

        return response()->json([
            'success' => true,
            'balance' => $record->balance,
            'invoice_value' => $record->invoice_value,
            'freight' => $record->freight,
            'paid' => $record->paid,
            'supplier_payments' => $record->supplier_payments ?? [],
            'china_load' => [
                'mbl' => $chinaLoad->mbl,
                'obl' => $chinaLoad->obl,
                'container_no' => $chinaLoad->container_no,
                'item' => $chinaLoad->item,
            ],
            'record'  => $record->only(array_merge($editable, ['id', 'container_sl_no', 'balance', 'supplier_payments'])),
        ]);
    }
}
