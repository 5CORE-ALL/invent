<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Models\ImageAuditHistory;
use App\Models\ProductMaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class ImageAuditController extends Controller
{
    /** Green while the latest audit is newer than this many days. */
    private const GREEN_WINDOW_DAYS = 30;

    public function index()
    {
        return view('image-audit');
    }

    /**
     * One row per product SKU with image coverage and audit history.
     * Parent grouping rows are left out. Oldest (or never) audited first.
     */
    public function data(): JsonResponse
    {
        $slots = $this->imageSlots();
        $columns = array_values(array_unique(array_merge(['sku', 'parent'], $slots)));

        $products = ProductMaster::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->whereRaw("UPPER(TRIM(sku)) NOT LIKE 'PARENT %'")
            ->orderBy('sku')
            ->get($columns);

        $historyBySku = collect();
        if (Schema::hasTable('image_audit_histories')) {
            $historyBySku = ImageAuditHistory::orderBy('created_at')
                ->get()
                ->groupBy(fn ($h) => $this->normalizeSku((string) $h->sku));
        }

        $now = Carbon::now();
        $data = [];
        foreach ($products as $product) {
            $sku = $this->normalizeSku((string) $product->sku);
            if ($sku === '') {
                continue;
            }

            $urls = [];
            foreach ($slots as $slot) {
                $url = $this->publicUrl($product->{$slot} ?? null);
                if ($url !== null) {
                    $urls[] = $url;
                }
            }

            $history = $historyBySku->get($sku, collect());
            $state = $this->auditState($history, $now);

            $data[] = [
                'sku' => $sku,
                'parent' => (string) ($product->parent ?? ''),
                'thumb' => $urls[0] ?? null,
                'image_count' => count($urls),
                'missing' => $urls === [],
                'dot' => $state['dot'],
                'green' => $state['green'],
                'stale' => $state['stale'],
                'latest_audit_at' => $state['latest_audit_at'],
                'latest_audit_ts' => $state['latest_audit_ts'],
                'history' => $state['history'],
            ];
        }

        usort($data, function ($a, $b) {
            if ($a['missing'] !== $b['missing']) {
                return $a['missing'] ? -1 : 1;
            }

            return $a['latest_audit_ts'] <=> $b['latest_audit_ts'];
        });

        return response()->json(['data' => $data]);
    }

    public function save(Request $request): JsonResponse
    {
        if (! Schema::hasTable('image_audit_histories')) {
            return response()->json([
                'ok' => false,
                'message' => 'Image audit history is not available yet. Run migrations.',
            ], 503);
        }

        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:255'],
            'fixed' => ['required', 'boolean'],
            'details' => ['required', 'string'],
        ]);

        $sku = $this->normalizeSku($validated['sku']);
        $exists = ProductMaster::query()
            ->whereRaw("REPLACE(TRIM(sku), CHAR(160), ' ') = ?", [$sku])
            ->exists();
        if (! $exists) {
            return response()->json([
                'ok' => false,
                'message' => 'SKU was not found.',
            ], 422);
        }

        ImageAuditHistory::create([
            'sku' => $sku,
            'fixed' => (bool) $validated['fixed'],
            'details' => $validated['details'],
            'user_id' => Auth::id(),
            'created_at' => Carbon::now(),
        ]);

        $history = ImageAuditHistory::where('sku', $sku)->orderBy('created_at')->get();
        $state = $this->auditState($history);

        return response()->json([
            'ok' => true,
            'dot' => $state['dot'],
            'green' => $state['green'],
            'stale' => $state['stale'],
            'latest_audit_at' => $state['latest_audit_at'],
            'history' => $state['history'],
        ]);
    }

    /**
     * @return list<string>
     */
    private function imageSlots(): array
    {
        $slots = ['main_image'];
        for ($i = 1; $i <= 20; $i++) {
            $slots[] = 'image'.$i;
        }

        return array_values(array_filter(
            $slots,
            fn (string $column) => Schema::hasColumn('product_master', $column)
        ));
    }

    private function normalizeSku(string $sku): string
    {
        return str_replace("\u{00a0}", ' ', trim($sku));
    }

    private function publicUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (str_starts_with($value, 'http') || str_starts_with($value, '//')) {
            return $value;
        }

        return '/'.ltrim($value, '/');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ImageAuditHistory>  $history
     * @return array{history: list<array{fixed: bool, details: string, created_at: string|null}>, dot: string, green: string, stale: bool, latest_audit_at: string|null, latest_audit_ts: int}
     */
    private function auditState($history, ?Carbon $now = null): array
    {
        $now = $now ?? Carbon::now();
        $historyArr = [];
        foreach ($history as $h) {
            $historyArr[] = [
                'fixed' => (bool) $h->fixed,
                'details' => (string) $h->details,
                'created_at' => optional($h->created_at)->format('Y-m-d H:i'),
            ];
        }
        $latest = $history->last();
        $latestAt = $latest && $latest->created_at ? $latest->created_at : null;
        $hasHistory = $latestAt !== null;
        $isRecent = $hasHistory && $latestAt->gt($now->copy()->subDays(self::GREEN_WINDOW_DAYS));

        return [
            'history' => $historyArr,
            'dot' => $isRecent ? 'green' : 'red',
            'green' => $hasHistory ? 'green' : 'red',
            'stale' => $hasHistory && ! $isRecent,
            'latest_audit_at' => $latestAt ? $latestAt->format('Y-m-d H:i') : null,
            'latest_audit_ts' => $latestAt ? $latestAt->getTimestamp() : 0,
        ];
    }
}
