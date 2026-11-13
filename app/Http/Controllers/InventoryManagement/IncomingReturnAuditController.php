<?php

namespace App\Http\Controllers\InventoryManagement;

use App\Http\Controllers\Controller;
use App\Models\IncomingReturnAudit;
use App\Models\IncomingReturnAuditPoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Audit checklist for the Incoming Return page: spot-check that claimed incentives are sufficient and legit.
 * The checklist points are a shared, editable template; every submitted audit is stored in history
 * with the user, date and time.
 */
class IncomingReturnAuditController extends Controller
{
    public function points(): JsonResponse
    {
        return response()->json(['data' => $this->pointRows()]);
    }

    public function storePoint(Request $request): JsonResponse
    {
        $validated = $request->validate(['description' => 'required|string|max:1000']);

        $point = IncomingReturnAuditPoint::query()->create([
            'description' => trim($validated['description']),
            'sort_order' => (int) IncomingReturnAuditPoint::query()->max('sort_order') + 1,
        ]);

        return response()->json(['success' => true, 'point' => $this->presentPoint($point)]);
    }

    public function updatePoint(Request $request, IncomingReturnAuditPoint $point): JsonResponse
    {
        $validated = $request->validate(['description' => 'required|string|max:1000']);

        $point->update(['description' => trim($validated['description'])]);

        return response()->json(['success' => true, 'point' => $this->presentPoint($point)]);
    }

    public function destroyPoint(IncomingReturnAuditPoint $point): JsonResponse
    {
        $point->delete();

        return response()->json(['success' => true]);
    }

    public function history(): JsonResponse
    {
        $rows = IncomingReturnAudit::query()
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (IncomingReturnAudit $a) => [
                'id' => $a->id,
                'user_name' => $a->user_name ?: '—',
                'date' => $a->created_at?->timezone(config('app.timezone'))->format('d M Y'),
                'time' => $a->created_at?->timezone(config('app.timezone'))->format('h:i A T'),
                'recovered_usd' => $a->recovered_usd,
                'incentive_rs' => $a->incentive_rs,
                'passed_count' => $a->passed_count,
                'total_count' => $a->total_count,
                'remarks' => $a->remarks,
                'items' => $a->items ?? [],
                'sample' => $a->sample ?? [],
            ])
            ->values();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1|max:100',
            'items.*.point_id' => 'nullable|integer',
            'items.*.description' => 'required|string|max:1000',
            'items.*.checked' => 'required|boolean',
            'remarks' => 'nullable|string|max:2000',
            'recovered_usd' => 'nullable|numeric',
            'incentive_rs' => 'nullable|numeric',
            'sample' => 'nullable|array|max:50',
            'sample.*' => 'array',
        ]);

        $items = collect($validated['items'])->map(fn ($i) => [
            'point_id' => $i['point_id'] ?? null,
            'description' => trim((string) $i['description']),
            'checked' => (bool) $i['checked'],
        ])->values();

        $user = Auth::user();

        $audit = IncomingReturnAudit::query()->create([
            'user_id' => $user?->id,
            'user_name' => trim((string) ($user?->name ?? '')) ?: null,
            'recovered_usd' => $validated['recovered_usd'] ?? null,
            'incentive_rs' => $validated['incentive_rs'] ?? null,
            'passed_count' => $items->where('checked', true)->count(),
            'total_count' => $items->count(),
            'items' => $items->all(),
            'sample' => $this->cleanSample($validated['sample'] ?? []),
            'remarks' => isset($validated['remarks']) ? trim($validated['remarks']) ?: null : null,
        ]);

        return response()->json(['success' => true, 'id' => $audit->id]);
    }

    /**
     * Keep only the known, scalar fields of the sampled rows.
     *
     * @param  array<int, array<string, mixed>>  $sample
     * @return list<array<string, mixed>>
     */
    protected function cleanSample(array $sample): array
    {
        $keys = ['sku', 'order_id', 'channel', 'restock_fee_usd', 'recovered_cost_usd', 'record_type_label'];

        return collect($sample)->map(function ($row) use ($keys) {
            $out = [];
            foreach ($keys as $k) {
                $v = $row[$k] ?? null;
                $out[$k] = is_scalar($v) ? $v : null;
            }

            return $out;
        })->values()->all();
    }

    /**
     * @return list<array{id:int, description:string}>
     */
    protected function pointRows(): array
    {
        return IncomingReturnAuditPoint::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (IncomingReturnAuditPoint $p) => $this->presentPoint($p))
            ->values()
            ->all();
    }

    /**
     * @return array{id:int, description:string}
     */
    protected function presentPoint(IncomingReturnAuditPoint $p): array
    {
        return ['id' => (int) $p->id, 'description' => (string) $p->description];
    }
}
