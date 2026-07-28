<?php

namespace App\Http\Controllers;

use App\Models\ImageMarketplaceMap;
use App\Models\Marketplace;
use App\Models\MarketplacePercentage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SkuImageController extends Controller
{
    public function pushStatus(Request $request): View
    {
        $marketplaceId = $request->integer('marketplace_id') ?: null;
        $statusFilter = $request->get('status');
        $qSku = $request->get('sku');

        $validStatuses = [
            ImageMarketplaceMap::STATUS_PENDING,
            ImageMarketplaceMap::STATUS_SENT,
            ImageMarketplaceMap::STATUS_FAILED,
        ];
        if ($statusFilter !== null && $statusFilter !== '' && ! in_array($statusFilter, $validStatuses, true)) {
            $statusFilter = null;
        } elseif ($statusFilter === '') {
            $statusFilter = null;
        }

        $codes = array_keys($this->marketplacePushDefinitions());

        $summaryRows = ImageMarketplaceMap::query()
            ->whereHas('marketplace', static function ($mq) use ($codes): void {
                $mq->whereIn(DB::raw('LOWER(TRIM(code))'), $codes);
            })
            ->select('marketplace_id', 'status', DB::raw('count(*) as c'))
            ->groupBy('marketplace_id', 'status')
            ->get();

        $marketplaces = Marketplace::query()
            ->whereIn(DB::raw('LOWER(TRIM(code))'), $codes)
            ->orderBy('name')
            ->get();
        $summary = [];
        foreach ($marketplaces as $mp) {
            $summary[$mp->id] = [
                'marketplace' => $mp,
                'pending' => 0,
                'sent' => 0,
                'failed' => 0,
                'total' => 0,
            ];
        }
        foreach ($summaryRows as $row) {
            $mid = (int) $row->marketplace_id;
            if (! isset($summary[$mid])) {
                $summary[$mid] = [
                    'marketplace' => $marketplaces->firstWhere('id', $mid),
                    'pending' => 0,
                    'sent' => 0,
                    'failed' => 0,
                    'total' => 0,
                ];
            }
            $c = (int) $row->c;
            $st = (string) $row->status;
            $summary[$mid][$st] = $c;
            $summary[$mid]['total'] += $c;
        }
        uasort(
            $summary,
            static function (array $a, array $b) {
                $la = MarketplacePercentage::displayNameForMarketplace($a['marketplace'])
                    ?? $a['marketplace']?->name
                    ?? '';
                $lb = MarketplacePercentage::displayNameForMarketplace($b['marketplace'])
                    ?? $b['marketplace']?->name
                    ?? '';

                return strcasecmp((string) $la, (string) $lb);
            }
        );

        $maps = ImageMarketplaceMap::query()
            ->with(['marketplace', 'skuImage.product'])
            ->whereHas('marketplace', static function ($mq) use ($codes): void {
                $mq->whereIn(DB::raw('LOWER(TRIM(code))'), $codes);
            })
            ->when($marketplaceId, static fn ($q) => $q->where('marketplace_id', $marketplaceId))
            ->when($statusFilter, static fn ($q) => $q->where('status', $statusFilter))
            ->when($qSku, function ($q) use ($qSku) {
                $s = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($qSku)).'%';
                $q->whereHas('skuImage.product', static function ($p) use ($s) {
                    $p->where('sku', 'like', $s);
                });
            })
            ->orderByDesc('updated_at')
            ->paginate(50)
            ->withQueryString();

        return view('sku_images.push_status', [
            'title' => 'SKU image push status',
            'maps' => $maps,
            'summary' => $summary,
            'filterMarketplaceId' => $marketplaceId,
            'filterStatus' => $statusFilter,
            'filterSku' => $qSku ? (string) $qSku : '',
        ]);
    }

    /**
     * @return array<string, array{label: string, short: string, class: string, enabled: bool}>
     */
    private function marketplacePushDefinitions(): array
    {
        return [
            'ebay' => ['label' => 'eBay 1', 'short' => 'E1', 'class' => 'btn-ebay1', 'enabled' => true],
            'ebay2' => ['label' => 'eBay 2', 'short' => 'E2', 'class' => 'btn-ebay2', 'enabled' => true],
            'ebay3' => ['label' => 'eBay 3', 'short' => 'E3', 'class' => 'btn-ebay3', 'enabled' => true],
            'macy' => ['label' => "Macy's", 'short' => 'M', 'class' => 'btn-macy', 'enabled' => true],
            'amazon' => ['label' => 'Amazon', 'short' => 'A', 'class' => 'btn-amazon', 'enabled' => true],
            'temu' => ['label' => 'Temu', 'short' => 'T', 'class' => 'btn-temu', 'enabled' => true],
            'reverb' => ['label' => 'Reverb', 'short' => 'R', 'class' => 'btn-reverb', 'enabled' => true],
            'wayfair' => ['label' => 'Wayfair', 'short' => 'W', 'class' => 'btn-wayfair', 'enabled' => true],
            'bestbuy' => ['label' => 'Best Buy', 'short' => 'B', 'class' => 'btn-bestbuy', 'enabled' => true],
            'shopify_main' => ['label' => 'Shopify Main', 'short' => 'SM', 'class' => 'btn-shopify', 'enabled' => true],
            'shopify_pls' => ['label' => 'Shopify PLS', 'short' => 'PLS', 'class' => 'btn-shopify-pls', 'enabled' => true],
        ];
    }
}
