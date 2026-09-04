<?php

namespace App\Support;

class AmazonCampaignMatcher
{
    /**
     * Match campaign by SKU first, then fallback to PARENT_SKU (only for parent rows).
     * PARENT_SKU fallback is ONLY for parent rows to avoid double-counting when parent
     * campaign would match multiple children.
     */
    public static function matchKwCampaign($collection, string $sku, ?string $parent, bool $allowParentFallback = false): ?object
    {
        $cleanSku = strtoupper(trim(rtrim($sku, '.')));
        $match = $collection->first(function ($item) use ($cleanSku) {
            $cn = strtoupper(trim(rtrim($item->campaignName ?? '', '.')));
            return $cn === $cleanSku;
        });
        if ($match) {
            return $match;
        }
        if ($allowParentFallback && $parent) {
            $cleanParent = strtoupper(trim(rtrim($parent, '.')));
            return $collection->first(function ($item) use ($cleanParent) {
                $cn = strtoupper(trim(rtrim($item->campaignName ?? '', '.')));
                return $cn === $cleanParent;
            }) ?: null;
        }
        return null;
    }

    /**
     * Match PT campaign: try SKU first, then PARENT_SKU (only when allowParentFallback).
     *
     * @param  bool  $requireEnabled  For missing-ads we check existence, for metrics we require ENABLED
     * @param  bool  $allowParentFallback  Only true for parent rows to avoid double-counting
     */
    public static function matchPtCampaign($collection, string $sku, ?string $parent, bool $requireEnabled = true, bool $allowParentFallback = false): ?object
    {
        $cleanSku = strtoupper(trim($sku));
        $match = $collection->first(function ($item) use ($cleanSku, $requireEnabled) {
            $cn = strtoupper(trim($item->campaignName ?? ''));
            $ok = (str_ends_with($cn, $cleanSku . ' PT') || str_ends_with($cn, $cleanSku . ' PT.'));
            return $ok && (! $requireEnabled || strtoupper($item->campaignStatus ?? '') === 'ENABLED');
        });
        if ($match) {
            return $match;
        }
        if ($allowParentFallback && $parent) {
            $cleanParent = strtoupper(trim($parent));
            return $collection->first(function ($item) use ($cleanParent, $requireEnabled) {
                $cn = strtoupper(trim($item->campaignName ?? ''));
                $ok = (str_ends_with($cn, $cleanParent . ' PT') || str_ends_with($cn, $cleanParent . ' PT.'));
                return $ok && (! $requireEnabled || strtoupper($item->campaignStatus ?? '') === 'ENABLED');
            }) ?: null;
        }
        return null;
    }

    /**
     * Match HL campaign: try SKU first, then PARENT_SKU (only when allowParentFallback).
     */
    public static function matchHlCampaign($collection, string $sku, ?string $parent, bool $allowParentFallback = false): ?object
    {
        $cleanSku = strtoupper(trim($sku));
        $candidates = [$cleanSku, $cleanSku . ' HEAD'];
        $match = $collection->first(function ($item) use ($candidates) {
            $cn = strtoupper(trim($item->campaignName ?? ''));
            return in_array($cn, $candidates) && strtoupper($item->campaignStatus ?? '') === 'ENABLED';
        });
        if ($match) {
            return $match;
        }
        if ($allowParentFallback && $parent) {
            $cleanParent = strtoupper(trim($parent));
            $parentCandidates = [$cleanParent, $cleanParent . ' HEAD'];
            return $collection->first(function ($item) use ($parentCandidates) {
                $cn = strtoupper(trim($item->campaignName ?? ''));
                return in_array($cn, $parentCandidates) && strtoupper($item->campaignStatus ?? '') === 'ENABLED';
            }) ?: null;
        }
        return null;
    }
}
