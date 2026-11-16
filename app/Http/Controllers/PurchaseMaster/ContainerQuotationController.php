<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\ContainerQuotation;
use App\Models\ContainerQuoteReceived;
use App\Models\Supplier;
use App\Models\TransitContainerDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ContainerQuotationController extends Controller
{
    private const FIELDS = [
        'supplier', 'container_size', 'pickup_address', 'loading_port', 'discharge_port',
        'gross_weight', 'freight_all_inclusive', 'forwarder',
    ];

    public function index()
    {
        $forwarders = ContainerQuotation::query()
            ->whereNotNull('forwarder')->where('forwarder', '!=', '')
            ->pluck('forwarder')
            ->merge(ContainerQuoteReceived::query()
                ->whereNotNull('forwarder')->where('forwarder', '!=', '')
                ->pluck('forwarder'))
            ->map(fn ($f) => trim((string) $f))->filter()->unique()->sort()->values();

        $suppliers = Supplier::where('type', 'Supplier')
            ->whereNotNull('name')->where('name', '!=', '')
            ->distinct()->orderBy('name')->pluck('name');

        return view('purchase-master.container_quotations.index', compact('forwarders', 'suppliers'));
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
                'discharge_port' => $q->discharge_port ?? null,
                'gross_weight' => $q->gross_weight ?? null,
                'freight_all_inclusive' => $q && $q->freight_all_inclusive !== null ? (float) $q->freight_all_inclusive : null,
                'forwarder' => $q->forwarder ?? null,
                'updated_by' => $q->updated_by ?? null,
                'updated_at' => $q && $q->updated_at ? $q->updated_at->format('d M Y, H:i') : null,
                'quotes' => $this->quoteRows($received->get($name)),
            ];
        }

        return response()->json($rows);
    }

    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            'container_number' => 'required|string|max:255',
            'supplier' => 'nullable|string|max:255',
            'container_size' => 'nullable|string|max:50',
            'pickup_address' => 'nullable|string|max:2000',
            'loading_port' => 'nullable|string|max:255',
            'discharge_port' => 'nullable|string|max:255',
            'gross_weight' => 'nullable|string|max:100',
            'freight_all_inclusive' => 'nullable|numeric|min:0',
            'forwarder' => 'nullable|string|max:255',
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
                'discharge_port' => $quote->discharge_port,
                'gross_weight' => $quote->gross_weight,
                'freight_all_inclusive' => $quote->freight_all_inclusive !== null ? (float) $quote->freight_all_inclusive : null,
                'forwarder' => $quote->forwarder,
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
        $data = $request->validate([
            'container_number' => 'required|string|max:255',
            'quotes' => 'nullable|array|max:50',
            'quotes.*.forwarder' => 'nullable|string|max:255',
            'quotes.*.pickup_charges' => 'nullable|numeric|min:0',
            'quotes.*.freight' => 'nullable|numeric|min:0',
        ]);

        $container = trim($data['container_number']);
        if (! in_array($container, $this->readyToShipContainers(), true)) {
            return response()->json(['success' => false, 'message' => 'Unknown container.'], 422);
        }

        $user = trim((string) (Auth::user()->name ?? '')) ?: 'System';
        $rows = [];
        foreach (($data['quotes'] ?? []) as $i => $q) {
            $fwd = trim((string) ($q['forwarder'] ?? ''));
            $pickup = ($q['pickup_charges'] ?? '') === '' ? null : (float) $q['pickup_charges'];
            $freight = ($q['freight'] ?? '') === '' ? null : (float) $q['freight'];
            if ($fwd === '' && $pickup === null && $freight === null) {
                continue; // completely empty row
            }
            if ($fwd === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Row '.($i + 1).': please enter the forwarder.',
                ], 422);
            }
            $rows[] = [
                'container_number' => $container,
                'forwarder' => $fwd,
                'pickup_charges' => $pickup,
                'freight' => $freight,
                'total' => round(($pickup ?? 0) + ($freight ?? 0), 2),
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
            'forwarder' => $r->forwarder,
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
    private function readyToShipContainers(): array
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