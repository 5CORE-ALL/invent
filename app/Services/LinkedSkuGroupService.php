<?php

namespace App\Services;

use App\Models\ComparisonSkuLink;
use App\Models\RfqForm;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LinkedSkuGroupService
{
    /** @var array<string, string>|null */
    private ?array $ufParent = null;

    /** @var array<string, string> */
    private array $displayByNorm = [];

    public function __construct(
        private ComparisonSkuLinkService $skuLinkService
    ) {
    }

    public function reset(): void
    {
        $this->ufParent = null;
        $this->displayByNorm = [];
        $this->groupByNormCache = null;
    }

    /**
     * Build union-find data for a page of SKUs without loading every stored link.
     *
     * @param  list<string>  $skus
     */
    public function prepareForSkus(array $skus): void
    {
        $this->reset();
        $this->ufParent = [];
        $this->displayByNorm = [];

        $this->loadStaticLinkSources();
        $this->loadComparisonLinksForSkus($skus);
    }

    /**
     * @param  list<string>  $skus
     */
    private function loadComparisonLinksForSkus(array $skus): void
    {
        $normList = [];
        foreach ($skus as $sku) {
            $norm = strtoupper(trim((string) $sku));
            if ($norm !== '') {
                $normList[] = $norm;
            }
        }

        $normList = array_values(array_unique($normList));
        if ($normList === []) {
            return;
        }

        foreach (array_chunk($normList, 100) as $chunk) {
            ComparisonSkuLink::query()
                ->select(['id', 'sku', 'linked_sku'])
                ->where(function ($query) use ($chunk) {
                    $query->whereIn('sku_norm', $chunk)
                        ->orWhereIn('linked_sku_norm', $chunk);
                })
                ->orderBy('id')
                ->chunkById(5000, function ($pairs) {
                    foreach ($pairs as $pair) {
                        $this->unionGroup([$pair->sku, $pair->linked_sku]);
                    }
                });
        }
    }

    private function loadStaticLinkSources(): void
    {
        foreach (RfqForm::query()->whereNotNull('linked_skus')->get(['linked_skus']) as $form) {
            $linked = $form->linked_skus;
            if (! is_array($linked)) {
                $linked = json_decode((string) $linked, true) ?: [];
            }
            $this->unionGroup($linked);
        }

        foreach (Supplier::query()->whereNotNull('sku')->where('sku', 'like', '%,%')->get(['sku']) as $supplier) {
            $tokens = preg_split('/\s*,\s*/', (string) ($supplier->sku ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (count($tokens) > 1) {
                $this->unionGroup($tokens);
            }
        }
    }

    /**
     * @param  list<string>  $linkedSkus
     * @return list<string>
     */
    public function normalizeGroup(string $sku, array $linkedSkus = []): array
    {
        $sku = trim($sku);
        $seeds = array_values(array_unique(array_filter(array_merge(
            [$sku],
            array_map(fn ($value) => trim((string) $value), $linkedSkus)
        ))));

        if ($seeds === []) {
            return [];
        }

        if (count($seeds) === 1) {
            return $this->groupContaining($seeds[0]);
        }

        $firstGroup = $this->groupContaining($seeds[0]);
        $firstNormSet = array_flip(array_map('strtoupper', $firstGroup));
        $sameGroup = true;
        foreach ($seeds as $seed) {
            if (! isset($firstNormSet[strtoupper($seed)])) {
                $sameGroup = false;
                break;
            }
        }

        if ($sameGroup) {
            return $firstGroup;
        }

        $group = [];
        foreach ($seeds as $seedSku) {
            if ($seedSku === '') {
                continue;
            }
            foreach ($this->groupContaining($seedSku) as $memberSku) {
                $group[$memberSku] = true;
            }
        }

        return array_keys($group);
    }

    /** @var array<string, list<string>>|null */
    private ?array $groupByNormCache = null;

    /**
     * @return list<string>
     */
    public function groupContaining(string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return [];
        }

        $cache = $this->buildGroupCache();

        return $cache[strtoupper($sku)] ?? [$sku];
    }

    /**
     * @return array<string, list<string>>
     */
    private function buildGroupCache(): array
    {
        if ($this->groupByNormCache !== null) {
            return $this->groupByNormCache;
        }

        $this->ensureBuilt();

        $groupsByRoot = [];
        foreach (array_keys($this->ufParent) as $memberNorm) {
            $root = $this->findRoot($memberNorm);
            $groupsByRoot[$root][] = $this->displayByNorm[$memberNorm] ?? $memberNorm;
        }

        $cache = [];
        foreach ($groupsByRoot as $members) {
            $group = array_values(array_unique(array_filter(array_map('trim', $members))));
            if ($group === []) {
                continue;
            }
            foreach ($group as $memberSku) {
                $cache[strtoupper($memberSku)] = $group;
            }
        }

        $this->groupByNormCache = $cache;

        return $cache;
    }

    /**
     * @param  list<string>  $skuGroup
     * @return array{clink: string, clink_sku: string|null}
     */
    public function resolveSharedClink(array $skuGroup): array
    {
        $fallbackSku = trim((string) ($skuGroup[0] ?? ''));
        $bestClink = '';
        $bestSku = null;
        $bestTs = null;

        foreach ($skuGroup as $candidateSku) {
            $candidateSku = trim((string) $candidateSku);
            if ($candidateSku === '') {
                continue;
            }

            $row = DB::table('forecast_analysis')
                ->whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper($candidateSku)])
                ->first(['sku', 'clink', 'updated_at']);

            $clink = trim((string) ($row->clink ?? ''));
            if ($clink === '') {
                continue;
            }

            $timestamp = $row?->updated_at ? Carbon::parse($row->updated_at) : null;

            if ($bestSku === null) {
                $bestClink = $clink;
                $bestSku = trim((string) ($row->sku ?? $candidateSku));
                $bestTs = $timestamp;
                continue;
            }

            if ($timestamp && ($bestTs === null || $timestamp->gt($bestTs))) {
                $bestClink = $clink;
                $bestSku = trim((string) ($row->sku ?? $candidateSku));
                $bestTs = $timestamp;
            }
        }

        if ($bestClink === '' && $fallbackSku !== '') {
            $bestClink = $this->clinkForSku($fallbackSku);
            $bestSku = $fallbackSku;
        }

        return [
            'clink' => $bestClink,
            'clink_sku' => $bestSku,
        ];
    }

    /**
     * @param  list<string>  $linkedSkus
     * @return list<array{sku: string, clink: string}>
     */
    public function propagateClink(string $primarySku, string $clink, array $linkedSkus = []): array
    {
        $clink = trim($clink);
        $affected = [];

        foreach ($this->normalizeGroup($primarySku, $linkedSkus) as $targetSku) {
            $this->upsertClinkForSku($targetSku, $clink);
            $affected[] = [
                'sku' => $targetSku,
                'clink' => $clink,
            ];
        }

        return $affected;
    }

    private function clinkForSku(string $sku): string
    {
        $row = DB::table('forecast_analysis')
            ->whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper(trim($sku))])
            ->value('clink');

        return trim((string) ($row ?? ''));
    }

    private function upsertClinkForSku(string $sku, string $clink): void
    {
        $displaySku = trim($sku);
        $skuNorm = strtoupper($displaySku);
        if ($skuNorm === '') {
            return;
        }

        $exists = DB::table('forecast_analysis')
            ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuNorm])
            ->exists();

        if ($exists) {
            DB::table('forecast_analysis')
                ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuNorm])
                ->update(['clink' => $clink, 'updated_at' => now()]);
        } else {
            DB::table('forecast_analysis')->insert([
                'sku' => $displaySku,
                'clink' => $clink,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function ensureBuilt(): void
    {
        if ($this->ufParent !== null) {
            return;
        }

        $this->ufParent = [];
        $this->displayByNorm = [];

        $this->loadStaticLinkSources();

        ComparisonSkuLink::query()
            ->select(['id', 'sku', 'linked_sku'])
            ->orderBy('id')
            ->chunkById(5000, function ($pairs) {
                foreach ($pairs as $pair) {
                    $this->unionGroup([$pair->sku, $pair->linked_sku]);
                }
            });
    }

    /**
     * @param  list<mixed>  $skus
     */
    private function unionGroup(array $skus): void
    {
        $norms = [];
        foreach ($skus as $sku) {
            $display = trim((string) $sku);
            $norm = strtoupper($display);
            if ($norm === '') {
                continue;
            }
            $norms[] = $norm;
            $this->displayByNorm[$norm] = $display;
        }

        if ($norms === []) {
            return;
        }

        $anchor = $norms[0];
        for ($i = 1, $count = count($norms); $i < $count; $i++) {
            $this->unionPair($anchor, $norms[$i]);
        }
    }

    private function unionPair(string $leftNorm, string $rightNorm): void
    {
        $leftRoot = $this->findRoot($leftNorm);
        $rightRoot = $this->findRoot($rightNorm);

        if ($leftRoot !== $rightRoot) {
            $this->ufParent[$leftRoot] = $rightRoot;
        }
    }

    private function findRoot(string $norm): string
    {
        if (! isset($this->ufParent[$norm])) {
            $this->ufParent[$norm] = $norm;
        }

        if ($this->ufParent[$norm] !== $norm) {
            $this->ufParent[$norm] = $this->findRoot($this->ufParent[$norm]);
        }

        return $this->ufParent[$norm];
    }
}
