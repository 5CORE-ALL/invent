<?php

namespace App\Console\Commands;

use App\Models\AlibabaMetric;
use App\Services\AlibabaApiService;
use Illuminate\Console\Command;

class AlibabaCheckProductsCommand extends Command
{
    protected $signature = 'alibaba:check-products
                            {--sku=* : Only check these SKUs (repeat the option); default is every linked SKU}
                            {--limit=0 : Stop after N dead products (0 = no limit)}';

    protected $description = 'List linked Alibaba SKUs whose product id Alibaba no longer returns (deleted / id changed). Those can never take an inventory push.';

    public function handle(AlibabaApiService $api): int
    {
        $skus = array_values(array_filter(array_map('trim', (array) $this->option('sku'))));

        $query = AlibabaMetric::query()
            ->whereNotNull('product_id')
            ->where('product_id', '!=', '')
            ->where('sku', '!=', '')
            ->whereColumn('sku', '!=', 'product_id');
        if ($skus !== []) {
            $query->whereIn('sku', $skus);
        }

        $limit = max(0, (int) $this->option('limit'));
        $checked = 0;
        $dead = [];
        $seen = [];

        foreach ($query->orderBy('sku')->get(['sku', 'product_id']) as $row) {
            $productId = (string) $row->product_id;
            if (isset($seen[$productId])) {
                if ($seen[$productId] === false) {
                    $dead[] = [(string) $row->sku, $productId, 'not found'];
                }
                continue;
            }

            $info = $api->getProductInfo($productId);
            $data = is_array($info['data'] ?? null) ? $info['data'] : [];
            $found = ! empty($info['success'])
                && array_diff(array_keys($data), ['code', 'request_id', '_trace_id_']) !== [];
            $seen[$productId] = $found;
            $checked++;

            if (! $found) {
                $dead[] = [(string) $row->sku, $productId, empty($info['success']) ? (string) ($info['message'] ?? 'API error') : 'not found'];
                if ($limit > 0 && count($dead) >= $limit) {
                    break;
                }
            }
            usleep(150000);
        }

        $this->info("Checked {$checked} Alibaba product(s).");
        if ($dead === []) {
            $this->info('All checked products exist on Alibaba.');

            return self::SUCCESS;
        }

        $this->warn(count($dead).' SKU(s) point to a product Alibaba does not return:');
        $this->table(['SKU', 'Alibaba product id', 'Result'], $dead);

        return self::SUCCESS;
    }
}
