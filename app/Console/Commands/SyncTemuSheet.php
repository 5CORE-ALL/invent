<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\ApiController;
use App\Models\TemuProductSheet;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class SyncTemuSheet extends Command
{
    protected $signature = 'sync:temu-sheet';
    protected $description = 'Sync Temu Product Sheet';

    protected $apiUrl;
    protected $apiKey;
    protected $password;

    public function __construct()
    {
        parent::__construct();
        $this->apiUrl    = "https://" . config('services.shopify_5core.domain') . "/admin/api/2024-10";
        $this->apiKey    = config('services.shopify_5core.api_key');
        $this->password  = config('services.shopify_5core.password');
    }


        public function handle()
    {
        $this->info("Fetching Temu data from Shopify…");

        $today = Carbon::today();
        $l30Start = $today->copy()->subDays(30); // last 30 days
        $l60Start = $today->copy()->subDays(60); // last 60 to 31 days

        $l30End = $today;
        $l60End = $l30Start->copy()->subDay();

        $endpoint = "{$this->apiUrl}/orders.json?status=any&limit=250"
            . "&created_at_min={$l60Start->toIso8601String()}"
            . "&created_at_max={$l30End->toIso8601String()}";

        while ($endpoint) {
            $response = Http::withBasicAuth($this->apiKey, $this->password)->get($endpoint);

            if (!$response->successful()) {
                $this->error("Failed to fetch Shopify orders");
                $this->error("Status: " . $response->status());
                $this->error("Body: " . $response->body());
                return;
            }

            $orders = $response->json('orders') ?? [];
            $this->info("Fetched " . count($orders) . " orders…");

            // filter Temu orders
            $temuOrders = collect($orders)->filter(function ($order) {
                $tags = strtolower($order['tags'] ?? '');
                if (str_contains($tags, 'temu')) return true;

                if (!empty($order['note_attributes'])) {
                    foreach ($order['note_attributes'] as $attr) {
                        if (
                            strtolower($attr['name'] ?? '') === 'channel' &&
                            strtolower($attr['value'] ?? '') === 'temu'
                        ) return true;
                    }
                }

                $source = strtolower($order['source_name'] ?? '');
                return str_contains($source, 'temu');
            });

            // group by SKU
            $grouped = $temuOrders->flatMap(function ($order) {
                return collect($order['line_items'])->map(function ($item) use ($order) {
                    return [
                        'sku'        => $item['sku'],
                        'created_at' => $order['created_at'],
                        'price'      => $item['price'],
                    ];
                });
            })->groupBy('sku');

            foreach ($grouped as $sku => $items) {
                $l30 = $items->filter(fn($i) => Carbon::parse($i['created_at'])->between($l30Start, $l30End))->count();
                $l60 = $items->filter(fn($i) => Carbon::parse($i['created_at'])->between($l60Start, $l60End))->count();
                $price = $items->last()['price'] ?? 0;

                TemuProductSheet::updateOrCreate(
                    ['sku' => $sku],
                    [
                        'l30'   => $l30,
                        'l60'   => $l60,
                        'price' => $price,
                    ]
                );
            }

            // get next page from Link header
            $linkHeader = $response->header('Link');
            if ($linkHeader && preg_match('/<([^>]+)>; rel="next"/', $linkHeader, $matches)) {
                $endpoint = $matches[1]; // URL for next page
            } else {
                $endpoint = null;
            }
        }

        $this->info("Temu L30/L60/Price updated successfully");
    }
}
