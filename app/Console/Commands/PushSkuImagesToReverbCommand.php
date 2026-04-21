<?php

namespace App\Console\Commands;

use App\Models\ImageMarketplaceMap;
use App\Models\Marketplace;
use App\Models\Product;
use App\Services\SkuImageMarketplacePushProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PushSkuImagesToReverbCommand extends Command
{
    protected $signature = 'sku-images:push-reverb
                            {sku? : Optional product_master SKU — only maps for this SKU are processed}
                            {--status=pending : pending, failed, sent, or "all" (comma-separated)}
                            {--limit=50 : Max rows to process (oldest first by id)}';

    protected $description = 'Process SKU image pushes to Reverb synchronously (no queue). Use one SKU to debug when UI shows sent but Reverb has no photo.';

    public function handle(SkuImageMarketplacePushProcessor $processor): int
    {
        $skuArg = $this->argument('sku');
        $sku = $skuArg !== null && $skuArg !== '' ? trim((string) $skuArg) : null;

        $statusOpt = strtolower(trim((string) $this->option('status')));
        $allStatuses = [
            ImageMarketplaceMap::STATUS_PENDING,
            ImageMarketplaceMap::STATUS_FAILED,
            ImageMarketplaceMap::STATUS_SENT,
        ];
        if ($statusOpt === 'all') {
            $statuses = $allStatuses;
        } else {
            $statuses = array_map('trim', explode(',', (string) $this->option('status')));
            $statuses = array_values(array_intersect($statuses, $allStatuses));
        }
        if ($statuses === []) {
            $statuses = [ImageMarketplaceMap::STATUS_PENDING];
        }

        $limit = max(1, min(500, (int) $this->option('limit')));

        $reverbIds = Marketplace::query()
            ->where('status', true)
            ->whereRaw('LOWER(TRIM(code)) = ?', ['reverb'])
            ->pluck('id')
            ->all();

        if ($reverbIds === []) {
            $this->error('No active marketplace with code "reverb".');

            return self::FAILURE;
        }

        $q = ImageMarketplaceMap::query()
            ->with(['skuImage.product', 'marketplace'])
            ->whereIn('marketplace_id', $reverbIds)
            ->whereIn('status', $statuses)
            ->orderBy('id');

        if ($sku !== null && $sku !== '') {
            $skuCandidates = array_unique(array_filter([trim($sku), preg_replace('/\s+/u', ' ', trim($sku))]));
            $q->where(static function ($outer) use ($skuCandidates): void {
                foreach ($skuCandidates as $c) {
                    $outer->orWhereHas('skuImage.product', static function ($p) use ($c): void {
                        $p->whereRaw('LOWER(TRIM(sku)) = ?', [strtolower($c)]);
                    });
                }
            });
        }

        $maps = $q->limit($limit)->get();

        if ($maps->isEmpty()) {
            $this->warn('No matching image_marketplace_map rows.');
            if ($sku !== null && $sku !== '') {
                $this->printSkuDiagnostics($sku, $reverbIds, $statuses);
            } else {
                $this->line('Tip: pass a SKU, or use <fg=cyan>--status=all</> to include already-sent rows (re-push). Maps are created when you use <fg=cyan>Push selected</> in SKU Image Manager.');
            }

            return self::SUCCESS;
        }

        $this->info('Processing '.$maps->count().' row(s)'.($sku ? ' for SKU: '.$sku : '').'…');

        $headers = ['map_id', 'sku', 'file', 'status', 'listing_id', 'image_url (truncated)', 'message'];
        $rows = [];

        foreach ($maps as $map) {
            $processor->processMapById((int) $map->id);
            $map->refresh();
            $pSku = $map->skuImage?->product?->sku ?? '—';
            $file = $map->skuImage?->file_name ?? '—';
            $msg = '';
            $data = [];
            if (is_array($map->response)) {
                $msg = (string) ($map->response['message'] ?? $map->response['error'] ?? '');
                $data = is_array($map->response['data'] ?? null) ? $map->response['data'] : [];
                if ($msg === '') {
                    $msg = mb_substr(json_encode($map->response), 0, 100);
                }
            }
            $listingId = isset($data['listing_id']) ? (string) $data['listing_id'] : '—';
            $imgUrl = isset($data['image_url']) ? (string) $data['image_url'] : '';
            $urlShort = $imgUrl !== '' ? mb_substr($imgUrl, 0, 64).(strlen($imgUrl) > 64 ? '…' : '') : '—';
            $rows[] = [
                (string) $map->id,
                $pSku,
                $file,
                $map->status,
                $listingId,
                $urlShort,
                mb_substr($msg, 0, 80),
            ];
        }

        $this->table($headers, $rows);

        $sent = $maps->filter(fn ($m) => $m->status === ImageMarketplaceMap::STATUS_SENT)->count();
        $failed = $maps->filter(fn ($m) => $m->status === ImageMarketplaceMap::STATUS_FAILED)->count();

        $this->newLine();
        $this->line('Reverb returned HTTP success for these rows. If photos still do not show on reverb.com: wait a few minutes (Reverb processes URLs async), hard-refresh the listing, confirm <fg=cyan>APP_URL</> is HTTPS and reachable from the public internet (open <fg=cyan>image_url</> in an incognito window).');

        return $failed > 0 && $sent === 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<int|string>  $reverbIds
     * @param  list<string>  $statusesFilter
     */
    private function printSkuDiagnostics(string $sku, array $reverbIds, array $statusesFilter): void
    {
        $product = null;
        foreach (array_unique([trim($sku), preg_replace('/\s+/u', ' ', trim($sku))]) as $candidate) {
            if ($candidate === '') {
                continue;
            }
            $product = Product::query()
                ->whereRaw('LOWER(TRIM(sku)) = ?', [strtolower($candidate)])
                ->first();
            if ($product) {
                break;
            }
        }

        if (! $product) {
            $this->error('No row in product_master with that SKU (after trim / space normalize). Check spelling in CP Masters.');
            $likeNorm = strtolower(trim(preg_replace('/\s+/u', ' ', $sku) ?? ''));
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $likeNorm).'%';
            $hints = Product::query()
                ->whereRaw('LOWER(sku) LIKE ?', [$like])
                ->limit(8)
                ->pluck('sku');
            if ($hints->isNotEmpty()) {
                $this->line('Similar SKUs in product_master:');
                foreach ($hints as $h) {
                    $this->line('  • '.$h);
                }
            }

            return;
        }

        $this->line('Found product id <fg=cyan>'.$product->id.'</> sku: <fg=cyan>'.$product->sku.'</>');
        $imgCount = $product->skuImages()->count();
        $this->line('sku_images for this product: <fg=cyan>'.$imgCount.'</>');

        $mapsAny = ImageMarketplaceMap::query()
            ->whereIn('marketplace_id', $reverbIds)
            ->whereHas('skuImage', static function ($q) use ($product): void {
                $q->where('product_id', $product->id);
            })
            ->select('status', DB::raw('count(*) as c'))
            ->groupBy('status')
            ->get();

        if ($mapsAny->isEmpty()) {
            $this->warn('No image_marketplace_map rows for Reverb on this product. Upload images in SKU Image Manager, select them, choose Reverb, and click <fg=cyan>Push selected</> first.');

            return;
        }

        $this->line('Reverb maps for this product (any status):');
        foreach ($mapsAny as $row) {
            $this->line('  • '.$row->status.': '.$row->c);
        }
        $filterStr = implode(', ', $statusesFilter);
        $this->line('Your filter was status in [<fg=cyan>'.$filterStr.'</>]. Rows already <fg=yellow>sent</> are skipped unless you use <fg=cyan>--status=all</> or <fg=cyan>--status=pending,failed,sent</>.');

        $sentCount = (int) ($mapsAny->firstWhere('status', ImageMarketplaceMap::STATUS_SENT)?->c ?? 0);
        $includesSent = in_array(ImageMarketplaceMap::STATUS_SENT, $statusesFilter, true);
        if ($sentCount > 0 && ! $includesSent) {
            $quoted = escapeshellarg($product->sku);
            $this->newLine();
            $this->line('To re-push those images to Reverb, run:');
            $this->line('  <fg=green>php artisan sku-images:push-reverb '.$quoted.' --status=all</>');
        }
    }
}
