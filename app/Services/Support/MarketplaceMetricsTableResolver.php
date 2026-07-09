<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Resolve metrics table names when migrations use singular/plural variants.
 */
class MarketplaceMetricsTableResolver
{
    /**
     * @var array<string, list<string>>
     */
    private const CANDIDATES = [
        'alibaba' => ['aliexpress_metrics', 'aliexpress_metric', 'alibaba_metrics'],
        'purchasing_power' => ['purchasing_power_metrics'],
        'faire' => ['faire_metrics'],
        'ebay2' => ['ebay_2_metrics', 'ebay2_metrics'],
        'ebay3' => ['ebay_3_metrics', 'ebay3_metrics'],
    ];

    public function table(string $marketplace): ?string
    {
        $marketplace = strtolower(trim($marketplace));
        $map = ProductMasterMarketplaceMaps::metricsTableMap();
        $preferred = $map[$marketplace] ?? null;

        if ($preferred && Schema::hasTable($preferred)) {
            return $preferred;
        }

        foreach (self::CANDIDATES[$marketplace] ?? [] as $candidate) {
            if (Schema::hasTable($candidate)) {
                return $candidate;
            }
        }

        return $preferred;
    }

    /**
     * @return array<string, string>
     */
    public function bulletTableMap(): array
    {
        $base = ProductMasterMarketplaceMaps::bulletServiceMap();
        $out = [];

        foreach (array_keys($base) as $marketplace) {
            $table = $this->table($marketplace);
            if ($table && Schema::hasTable($table)) {
                $out[$marketplace] = $table;
            }
        }

        return $out;
    }
}
