<?php

namespace App\Services;

use App\Models\AdsSkuLink;

class AdsSkuLinkService
{
    public function normalize(string $sku): string
    {
        return strtoupper(trim($sku));
    }

    public function link(string $sku, string $linkedSku, ?string $user = null): void
    {
        $sku = trim($sku);
        $linkedSku = trim($linkedSku);

        if ($sku === '' || $linkedSku === '') {
            return;
        }

        if ($this->normalize($sku) === $this->normalize($linkedSku)) {
            return;
        }

        foreach ([[$sku, $linkedSku], [$linkedSku, $sku]] as [$from, $to]) {
            AdsSkuLink::updateOrCreate(
                [
                    'sku_norm' => $this->normalize($from),
                    'linked_sku_norm' => $this->normalize($to),
                ],
                [
                    'sku' => $from,
                    'linked_sku' => $to,
                    'updated_by' => $user,
                ]
            );
        }
    }

    public function unlink(string $sku, string $linkedSku): void
    {
        $sku = trim($sku);
        $linkedSku = trim($linkedSku);

        if ($sku === '' || $linkedSku === '') {
            return;
        }

        $leftNorm = $this->normalize($sku);
        $rightNorm = $this->normalize($linkedSku);

        AdsSkuLink::query()
            ->where(function ($query) use ($leftNorm, $rightNorm) {
                $query->where('sku_norm', $leftNorm)->where('linked_sku_norm', $rightNorm);
            })
            ->orWhere(function ($query) use ($leftNorm, $rightNorm) {
                $query->where('sku_norm', $rightNorm)->where('linked_sku_norm', $leftNorm);
            })
            ->delete();
    }

    /**
     * Fully detach a SKU from a linked group.
     *
     * @param  list<string>  $groupMembers
     */
    public function unlinkFromGroup(string $skuToRemove, array $groupMembers, ?string $user = null): void
    {
        $removeNorm = $this->normalize($skuToRemove);
        if ($removeNorm === '') {
            return;
        }

        $memberNorms = [];
        $remaining = [];
        foreach ($groupMembers as $member) {
            $display = trim((string) $member);
            $norm = $this->normalize($display);
            if ($norm === '' || $norm === $removeNorm) {
                continue;
            }
            $memberNorms[$norm] = $norm;
            $remaining[$norm] = $display;
        }

        $memberNorms = array_values($memberNorms);
        if ($memberNorms !== []) {
            AdsSkuLink::query()
                ->where(function ($query) use ($removeNorm, $memberNorms) {
                    $query->where('sku_norm', $removeNorm)
                        ->whereIn('linked_sku_norm', $memberNorms);
                })
                ->orWhere(function ($query) use ($removeNorm, $memberNorms) {
                    $query->where('linked_sku_norm', $removeNorm)
                        ->whereIn('sku_norm', $memberNorms);
                })
                ->delete();
        }

        $this->syncFullyConnectedGroup(array_values($remaining), $user);
    }

    /**
     * @param  list<string>  $skus
     */
    public function syncFullyConnectedGroup(array $skus, ?string $user = null): void
    {
        $skus = array_values(array_unique(array_filter(array_map('trim', $skus))));

        for ($i = 0, $count = count($skus); $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $this->link($skus[$i], $skus[$j], $user);
            }
        }
    }
}
