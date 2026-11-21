<?php

namespace App\Support\Compliance;

use App\Support\Badges\ComplianceMasterBadgeCalculator;

/**
 * One dedicated "Compliance <Badge>" page per compliance badge (Battery, Wireless, ...).
 * Every page is the Compliance Masters table limited to that one field.
 */
class CompliancePages
{
    /**
     * slug => [field key (Values JSON / badge field), short badge label]
     *
     * @return array<string, array{field: string, label: string, title: string, url: string, route: string}>
     */
    public static function all(): array
    {
        $defs = [
            'battery' => ['battery', 'Battery'],
            'wireless' => ['wireless', 'Wireless'],
            'electric' => ['electric', 'Electric'],
            'gcc' => ['gcc', 'GCC'],
            'rohs' => ['rohs', 'RoHS'],
            'blanket' => ['blanket', 'Blanket'],
            'bluetooth' => ['bluetooth', 'Bluetooth'],
            'logo' => ['logo', 'Logo'],
            'graph' => ['graph', 'Graph'],
            'tsp' => ['tsp', 'TSP'],
            'batch' => ['batch', 'Batch'],
            'energy-star' => ['energy_star_registration', 'Energy Star'],
            'us-energy' => ['us_energy_efficiency_label', 'US Energy'],
        ];

        $out = [];
        foreach ($defs as $slug => [$field, $label]) {
            $out[$slug] = [
                'field' => $field,
                'label' => $label,
                'title' => 'Compliance '.$label,
                'url' => '/compliance-'.$slug,
                'route' => 'compliance.page.'.$slug,
            ];
        }

        return $out;
    }

    /** Pages with their current "missing REQ" count (cached, same numbers as the badges on Compliance Masters). */
    public static function withCounts(): array
    {
        $counts = ComplianceMasterBadgeCalculator::cachedCounts();
        $pages = self::all();
        foreach ($pages as $slug => $p) {
            $pages[$slug]['count'] = (int) ($counts['missing_'.$p['field']] ?? 0);
        }

        return $pages;
    }

    /** Sum of the per-badge counts shown on the sidebar group (all compliance pages together). */
    public static function totalCount(): int
    {
        return (int) array_sum(array_column(self::withCounts(), 'count'));
    }
}
