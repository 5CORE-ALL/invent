<?php

namespace App\Console\Commands;

use App\Models\TemuOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;


class ProbeTemuOrderAmount extends Command
{
    protected $signature = 'app:probe-temu-order-amount
        {--parent= : A specific parentOrderSn to probe}
        {--count=3 : How many recent distinct parent orders to probe}';

    protected $description = 'Probe Temu bg.order.amount.query and dump the raw response to discover amount fields';

    public function handle(): int
    {
        $appKey = config('services.temu.app_key');
        $appSecret = config('services.temu.secret_key');
        $accessToken = config('services.temu.access_token');

        if (empty($appKey) || empty($appSecret) || empty($accessToken)) {
            $this->error('Missing Temu API credentials in .env');

            return self::FAILURE;
        }

        $parents = [];
        if ($this->option('parent')) {
            $parents[] = (string) $this->option('parent');
        } else {
            $count = max(1, (int) $this->option('count'));
            $parents = TemuOrder::whereNotNull('parent_order_sn')
                ->orderBy('parent_order_time', 'desc')
                ->pluck('parent_order_sn')
                ->unique()
                ->take($count)
                ->values()
                ->all();
        }

        if (empty($parents)) {
            $this->error('No parentOrderSn found to probe.');

            return self::FAILURE;
        }

        foreach ($parents as $parentOrderSn) {
            $this->info("\n=== bg.order.amount.query for {$parentOrderSn} ===");

            $body = [
                'type' => 'bg.order.amount.query',
                'parentOrderSn' => $parentOrderSn,
            ];

            $signed = $this->generateSignValue($body);

            try {
                $response = Http::timeout(60)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post('https://openapi-b-us.temu.com/openapi/router', $signed);

                $this->line('HTTP ' . $response->status());
                $this->line(json_encode($response->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            } catch (\Throwable $e) {
                $this->error('Request error: ' . $e->getMessage());
            }

            usleep(400000);
        }

        return self::SUCCESS;
    }

    private function generateSignValue(array $requestBody): array
    {
        $appKey = config('services.temu.app_key');
        $appSecret = config('services.temu.secret_key');
        $accessToken = config('services.temu.access_token');

        $params = [
            'access_token' => $accessToken,
            'app_key' => $appKey,
            'timestamp' => time(),
            'data_type' => 'JSON',
        ];

        $signParams = array_merge($params, $requestBody);
        ksort($signParams);

        $temp = '';
        foreach ($signParams as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            $temp .= $key . $value;
        }

        $params['sign'] = strtoupper(md5($appSecret . $temp . $appSecret));

        return array_merge($params, $requestBody);
    }
}
