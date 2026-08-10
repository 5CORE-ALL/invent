<?php

namespace App\Services\MarketplaceManager;

use App\Models\TemuOrder;
use App\Services\TemuApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Pull tracking_number + carrier from Temu OpenAPI into temu_orders.
 * Used by Sales Order Fulfillment (no Shopify, no CSV upload).
 */
class TemuOrderTrackingPullService
{
    public function __construct(
        protected TemuApiService $temuApi,
    ) {}

    /**
     * @return array{success: bool, checked: int, updated: int, missing: int, failed: int, message: string}
     */
    public function pullPending(int $limit = 40, bool $refreshExisting = false): array
    {
        if (! $this->temuApi->isConfigured()) {
            return [
                'success' => false,
                'checked' => 0,
                'updated' => 0,
                'missing' => 0,
                'failed' => 0,
                'message' => 'Temu API credentials missing.',
            ];
        }

        if (! Schema::hasTable('temu_orders') || ! Schema::hasColumn('temu_orders', 'tracking_number')) {
            return [
                'success' => false,
                'checked' => 0,
                'updated' => 0,
                'missing' => 0,
                'failed' => 0,
                'message' => 'temu_orders.tracking_number missing — run migrations.',
            ];
        }

        $limit = max(1, min(200, $limit));
        $query = TemuOrder::query()
            ->whereNotNull('parent_order_sn')
            ->where('parent_order_sn', '!=', '')
            ->where(function ($q) {
                $q->whereRaw(
                    "UPPER(TRIM(COALESCE(parent_order_status_text, order_status_text, ''))) IN (?, ?, ?, ?)",
                    ['SHIPPED', 'PARTIALLY_SHIPPED', 'DELIVERED', 'PARTIALLY_DELIVERED']
                )->orWhereRaw(
                    "CAST(COALESCE(parent_order_status, order_status, 0) AS CHAR) IN (?, ?, ?)",
                    ['3', '4', '5']
                );
            });

        if (! $refreshExisting) {
            $query->where(function ($q) {
                $q->whereNull('tracking_number')->orWhere('tracking_number', '=', '');
            });
        }

        $parents = $query
            ->selectRaw('parent_order_sn, MAX(id) as max_id')
            ->groupBy('parent_order_sn')
            ->orderByDesc('max_id')
            ->limit($limit)
            ->pluck('parent_order_sn')
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->values();

        $checked = 0;
        $updated = 0;
        $missing = 0;
        $failed = 0;

        foreach ($parents as $parentOrderSn) {
            $checked++;
            $result = $this->pullForParentOrder($parentOrderSn, $refreshExisting);
            if (! empty($result['success']) && ! empty($result['updated'])) {
                $updated++;
            } elseif (! empty($result['missing'])) {
                $missing++;
            } elseif (empty($result['success'])) {
                $failed++;
            }
            usleep(150000);
        }

        return [
            'success' => $failed === 0,
            'checked' => $checked,
            'updated' => $updated,
            'missing' => $missing,
            'failed' => $failed,
            'message' => "Temu tracking pull: checked {$checked}, updated {$updated}, missing {$missing}, failed {$failed}.",
        ];
    }

    /**
     * @return array{success: bool, updated?: bool, missing?: bool, message: string, tracking_number?: ?string, carrier?: ?string}
     */
    public function pullForParentOrder(string $parentOrderSn, bool $refreshExisting = false): array
    {
        $parentOrderSn = trim($parentOrderSn);
        if ($parentOrderSn === '') {
            return ['success' => false, 'message' => 'parent_order_sn required.'];
        }

        if (! $this->temuApi->isConfigured()) {
            return ['success' => false, 'message' => 'Temu API credentials missing.'];
        }

        $shipment = $this->temuApi->getShipmentInfo($parentOrderSn);
        if (empty($shipment['success'])) {
            Log::info('TemuOrderTrackingPullService: shipment fetch failed', [
                'parent_order_sn' => $parentOrderSn,
                'message' => $shipment['message'] ?? null,
            ]);

            return [
                'success' => false,
                'message' => (string) ($shipment['message'] ?? 'Temu shipment fetch failed.'),
            ];
        }

        $tracking = trim((string) ($shipment['tracking_number'] ?? ''));
        $carrier = trim((string) ($shipment['carrier'] ?? ''));
        $packageSn = trim((string) ($shipment['package_sn'] ?? ''));

        if ($tracking === '' && $packageSn === '') {
            return [
                'success' => true,
                'missing' => true,
                'updated' => false,
                'message' => "No tracking on Temu yet for {$parentOrderSn}.",
                'tracking_number' => null,
                'carrier' => null,
            ];
        }

        $byOrderSn = [];
        foreach (($shipment['shipments'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $osn = trim((string) ($row['order_sn'] ?? ''));
            $tn = trim((string) ($row['tracking_number'] ?? ''));
            if ($osn === '' || $tn === '') {
                continue;
            }
            $byOrderSn[$osn] = $row;
        }

        $lines = TemuOrder::query()->where('parent_order_sn', $parentOrderSn)->get();
        if ($lines->isEmpty()) {
            return ['success' => false, 'message' => "No temu_orders rows for {$parentOrderSn}."];
        }

        $changed = 0;
        $now = now();
        foreach ($lines as $line) {
            $osn = trim((string) ($line->order_sn ?? ''));
            $hit = ($osn !== '' && isset($byOrderSn[$osn])) ? $byOrderSn[$osn] : null;
            $lineTracking = trim((string) ($hit['tracking_number'] ?? $tracking));
            $lineCarrier = trim((string) ($hit['carrier'] ?? $carrier));
            $linePackage = trim((string) ($hit['package_sn'] ?? $packageSn));

            if ($lineTracking === '' && $linePackage === '') {
                continue;
            }

            $existing = trim((string) ($line->tracking_number ?? ''));
            if (! $refreshExisting && $existing !== '' && strcasecmp($existing, $lineTracking) === 0) {
                continue;
            }

            $line->tracking_number = $lineTracking !== '' ? $lineTracking : ($line->tracking_number ?: null);
            if ($lineCarrier !== '') {
                $line->carrier = $lineCarrier;
            }
            if ($linePackage !== '') {
                $line->package_sn = $linePackage;
            }
            $line->tracking_fetched_at = $now;
            $line->save();
            $changed++;
        }

        return [
            'success' => true,
            'updated' => $changed > 0,
            'missing' => $changed === 0 && $tracking === '',
            'message' => $changed > 0
                ? "Updated {$changed} line(s) for {$parentOrderSn} → {$tracking}".($carrier !== '' ? " ({$carrier})" : '')
                : "No line updates for {$parentOrderSn}.",
            'tracking_number' => $tracking !== '' ? $tracking : null,
            'carrier' => $carrier !== '' ? $carrier : null,
        ];
    }
}
