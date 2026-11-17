<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\ContainerQuotation;
use App\Models\ContainerQuoteReceived;
use App\Models\ContainerQuoteRequest;
use App\Models\Supplier;
use App\Models\TransitContainerDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ContainerQuotationController extends Controller
{
    private const FIELDS = [
        'supplier', 'container_size', 'pickup_address', 'loading_port', 'via_port', 'discharge_port',
        'gross_weight',
    ];

    public function index()
    {
        $forwarders = ContainerQuoteReceived::query()
            ->whereNotNull('forwarder')->where('forwarder', '!=', '')
            ->pluck('forwarder')
            ->map(fn ($f) => trim((string) $f))->filter()->unique()->sort()->values();

        $suppliers = Supplier::where('type', 'Supplier')
            ->whereNotNull('name')->where('name', '!=', '')
            ->distinct()->orderBy('name')->pluck('name');

        $canEdit = $this->canFill();

        // Last pickup address saved for each supplier — reused to prefill the Q form next time.
        $supplierAddresses = [];
        ContainerQuotation::query()
            ->whereNotNull('supplier')->where('supplier', '!=', '')
            ->whereNotNull('pickup_address')->where('pickup_address', '!=', '')
            ->orderBy('updated_at')->orderBy('id')
            ->get(['supplier', 'pickup_address'])
            ->each(function ($q) use (&$supplierAddresses) {
                $supplierAddresses[mb_strtolower(trim((string) $q->supplier))] = $q->pickup_address;
            });

        return view('purchase-master.container_quotations.index', compact('forwarders', 'suppliers', 'canEdit', 'supplierAddresses'));
    }

    /**
     * One row per container listed on /ready-to-ship (open containers),
     * latest container first, joined with any saved quotation details.
     */
    public function data(): JsonResponse
    {
        $containers = $this->readyToShipContainers();
        $quotes = ContainerQuotation::whereIn('container_number', $containers)->get()->keyBy('container_number');
        $received = ContainerQuoteReceived::whereIn('container_number', $containers)
            ->orderBy('id')->get()->groupBy('container_number');
        $canFill = $this->canFill();
        $requests = [];
        if ($canFill && Schema::hasTable('container_quote_requests')) {
            $requestController = app(ContainerQuoteRequestController::class);
            foreach (ContainerQuoteRequest::whereIn('container_number', $containers)->pluck('container_number')->unique() as $cn) {
                $requests[$cn] = $requestController->requestRows($cn, true);
            }
        }

        $rows = [];
        foreach ($containers as $name) {
            $q = $quotes->get($name);
            $rows[] = [
                'container_number' => $name,
                'has_details' => (bool) $q,
                'supplier' => $q->supplier ?? null,
                'container_size' => $q->container_size ?? null,
                'pickup_address' => $q->pickup_address ?? null,
                'loading_port' => $q->loading_port ?? null,
                'via_port' => $q->via_port ?? null,
                'discharge_port' => $q->discharge_port ?? null,
                'gross_weight' => $q->gross_weight ?? null,
                'updated_by' => $q->updated_by ?? null,
                'updated_at' => $q && $q->updated_at ? $q->updated_at->format('d M Y, H:i') : null,
                'quotes' => $this->quoteRows($received->get($name)),
                'requests' => $requests[$name] ?? [],
            ];
        }

        return response()->json($rows);
    }

    public function save(Request $request): JsonResponse
    {
        if (! $this->canFill()) {
            return $this->forbidden();
        }

        $data = $request->validate([
            'container_number' => 'required|string|max:255',
            'supplier' => 'nullable|string|max:255',
            'container_size' => 'nullable|string|max:50',
            'pickup_address' => 'nullable|string|max:2000',
            'loading_port' => 'nullable|string|max:255',
            'via_port' => 'nullable|string|max:255',
            'discharge_port' => 'nullable|string|max:255',
            'gross_weight' => 'nullable|string|max:100',
        ]);

        $container = trim($data['container_number']);
        if (! in_array($container, $this->readyToShipContainers(), true)) {
            return response()->json(['success' => false, 'message' => 'Unknown container.'], 422);
        }

        $values = [];
        foreach (self::FIELDS as $f) {
            $v = $data[$f] ?? null;
            $values[$f] = is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v;
        }
        $values['updated_by'] = trim((string) (Auth::user()->name ?? '')) ?: 'System';

        $quote = ContainerQuotation::updateOrCreate(['container_number' => $container], $values);

        return response()->json([
            'success' => true,
            'row' => [
                'container_number' => $quote->container_number,
                'has_details' => true,
                'supplier' => $quote->supplier,
                'container_size' => $quote->container_size,
                'pickup_address' => $quote->pickup_address,
                'loading_port' => $quote->loading_port,
                'via_port' => $quote->via_port,
                'discharge_port' => $quote->discharge_port,
                'gross_weight' => $quote->gross_weight,
                'updated_by' => $quote->updated_by,
                'updated_at' => $quote->updated_at ? $quote->updated_at->format('d M Y, H:i') : null,
            ],
        ]);
    }

    /**
     * Replace the set of received forwarder quotes for one container.
     */
    public function saveQuotes(Request $request): JsonResponse
    {
        if (! $this->canFill()) {
            return $this->forbidden();
        }

        $data = $request->validate([
            'container_number' => 'required|string|max:255',
            'quotes' => 'nullable|array|max:50',
            'quotes.*.request_id' => 'nullable|integer',
            'quotes.*.forwarder' => 'nullable|string|max:255',
            'quotes.*.shipping_line' => 'nullable|string|max:255',
            'quotes.*.est_dt_sail' => 'nullable|date',
            'quotes.*.pickup_charges' => 'nullable|numeric|min:0',
            'quotes.*.freight' => 'nullable|numeric|min:0',
            'quotes.*.total' => 'nullable|numeric|min:0',
        ]);

        $container = trim($data['container_number']);
        if (! in_array($container, $this->readyToShipContainers(), true)) {
            return response()->json(['success' => false, 'message' => 'Unknown container.'], 422);
        }

        $user = trim((string) (Auth::user()->name ?? '')) ?: 'System';
        $validRequestIds = ContainerQuoteRequest::where('container_number', $container)->pluck('id')->all();
        $rows = [];
        foreach (($data['quotes'] ?? []) as $i => $q) {
            $fwd = trim((string) ($q['forwarder'] ?? ''));
            $pickup = ($q['pickup_charges'] ?? '') === '' ? null : (float) $q['pickup_charges'];
            $freight = ($q['freight'] ?? '') === '' ? null : (float) $q['freight'];
            $total = ($q['total'] ?? '') === '' ? null : round((float) $q['total'], 2);
            $reqId = isset($q['request_id']) && in_array((int) $q['request_id'], $validRequestIds, true)
                ? (int) $q['request_id'] : null;
            $line = trim((string) ($q['shipping_line'] ?? ''));
            $sail = trim((string) ($q['est_dt_sail'] ?? ''));
            if ($fwd === '' && $line === '' && $sail === '' && $pickup === null && $freight === null && $total === null) {
                continue; // completely empty row
            }
            if ($fwd === '' || $line === '' || $sail === '' || $pickup === null || $freight === null || $total === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Row '.($i + 1).': all fields are mandatory (Forwarder, Shipping Line, Est Dt Sail, Pickup Charges, Freight, Total).',
                ], 422);
            }
            if ($total < round($pickup + $freight, 2)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Row '.($i + 1).': Total must include all charges (at least Pickup Charges + Freight).',
                ], 422);
            }
            $rows[] = [
                'container_number' => $container,
                'request_id' => $reqId,
                'source' => $reqId ? 'forwarder' : 'internal',
                'forwarder' => $fwd,
                'shipping_line' => $line === '' ? null : $line,
                'est_dt_sail' => $sail === '' ? null : date('Y-m-d', strtotime($sail)),
                'pickup_charges' => $pickup,
                'freight' => $freight,
                'total' => $total,
                'updated_by' => $user,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::transaction(function () use ($container, $rows) {
            ContainerQuoteReceived::where('container_number', $container)->delete();
            if ($rows) {
                ContainerQuoteReceived::insert($rows);
            }
        });

        return response()->json([
            'success' => true,
            'container_number' => $container,
            'quotes' => $this->quoteRows(
                ContainerQuoteReceived::where('container_number', $container)->orderBy('id')->get()
            ),
        ]);
    }

    /** The quotation forms may only be filled in by 5Core team members (@5core.com). */
    private function canFill(): bool
    {
        $user = Auth::user();

        return $user && method_exists($user, 'is5CoreMember') && $user->is5CoreMember();
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Only 5Core users can fill in this form.',
        ], 403);
    }

    /**
     * @param  \Illuminate\Support\Collection|null  $rows
     * @return array<int, array<string, mixed>>
     */
    private function quoteRows($rows): array
    {
        if (! $rows) {
            return [];
        }

        return $rows->map(fn ($r) => [
            'request_id' => $r->request_id,
            'source' => $r->source,
            'forwarder' => $r->forwarder,
            'shipping_line' => $r->shipping_line,
            'est_dt_sail' => $r->est_dt_sail ? substr((string) $r->est_dt_sail, 0, 10) : null,
            'pickup_charges' => $r->pickup_charges !== null ? (float) $r->pickup_charges : null,
            'freight' => $r->freight !== null ? (float) $r->freight : null,
            'total' => $r->total !== null ? (float) $r->total : null,
        ])->values()->all();
    }
    /**
     * Same container list the /ready-to-ship page offers (open containers),
     * newest number first.
     *
     * @return array<int, string>
     */
    public function readyToShipContainers(): array
    {
        $names = TransitContainerDetail::where(function ($q) {
            $q->whereNull('status')->orWhereRaw("TRIM(status) = ''");
        })->distinct()->pluck('tab_name')
            ->map(fn ($n) => trim((string) $n))
            ->filter(fn ($n) => $n !== '')
            ->unique()->values()->all();

        usort($names, fn ($a, $b) => strnatcasecmp($b, $a));

        return $names;
    }
}