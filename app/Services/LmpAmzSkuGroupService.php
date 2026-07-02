<?php

namespace App\Services;

use App\Models\LmpAmzSkuLink;

class LmpAmzSkuGroupService
{
    /** @var array<string, string>|null */
    private ?array $ufParent = null;

    /** @var array<string, string> */
    private array $displayByNorm = [];

    /** @var array<string, list<string>>|null */
    private ?array $groupByNormCache = null;

    public function reset(): void
    {
        $this->ufParent = null;
        $this->displayByNorm = [];
        $this->groupByNormCache = null;
    }

    /**
     * @param  list<string>  $skus
     */
    public function prepareForSkus(array $skus): void
    {
        $this->reset();
        $this->ufParent = [];
        $this->displayByNorm = [];

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
            LmpAmzSkuLink::query()
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

        $groupsByRoot = [];
        foreach (array_keys($this->ufParent ?? []) as $memberNorm) {
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
