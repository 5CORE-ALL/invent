<?php

namespace App\Services;

use App\Models\LmpSkuLink;

class LmpSkuLinkService
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
            LmpSkuLink::updateOrCreate(
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

        LmpSkuLink::query()
            ->where(function ($query) use ($leftNorm, $rightNorm) {
                $query->where('sku_norm', $leftNorm)->where('linked_sku_norm', $rightNorm);
            })
            ->orWhere(function ($query) use ($leftNorm, $rightNorm) {
                $query->where('sku_norm', $rightNorm)->where('linked_sku_norm', $leftNorm);
            })
            ->delete();
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
