<?php

namespace App\Console\Commands;

use App\Models\TopDawgProduct;
use App\Services\TopDawgApiService;
use Illuminate\Console\Command;

/**
 * Probe TopDawg's price-push endpoint so we can wire up real price-push
 * functionality from the /topdawg-pricing page.
 *
 * TopDawg's public docs don't publish the price-update path explicitly, so we
 * try a curated list of likely endpoint + body shape + HTTP method
 * combinations and print the response for each. Whichever returns a 2xx is
 * the real one; once confirmed, hard-code those defaults in
 * `TopDawgApiService::pushPrice()` and the `/topdawg-pricing` UI can call it.
 *
 * USAGE
 *   # Try every candidate (read-only probe — TopDawg may still record the
 *   # attempts, so pick a low-traffic test SKU):
 *   php artisan topdawg:test-push-price --sku=YOUR-TEST-SKU --price=19.99
 *
 *   # Limit to a single endpoint / shape / method:
 *   php artisan topdawg:test-push-price --sku=ABC --price=19.99 \
 *       --endpoint=/SupplierProduct/update --shape=flat --method=POST
 *
 *   # Dry-run — print every request body without hitting the network:
 *   php artisan topdawg:test-push-price --sku=ABC --price=19.99 --dry-run
 */
class TestTopDawgPushPrice extends Command
{
    protected $signature = 'topdawg:test-push-price
        {--sku=        : SKU to test with (must exist in topdawg_products)}
        {--price=      : Price to push (e.g. 19.99). Required unless --dry-run}
        {--tdid=       : Override TDID (default: looked up from topdawg_products)}
        {--endpoint=   : Probe just this endpoint instead of all candidates}
        {--shape=      : Probe just this body shape instead of all candidates}
        {--method=     : Probe just this HTTP method (POST|PUT|PATCH)}
        {--dry-run     : Print the requests without hitting the network}
        {--all         : Probe every combo even after a 2xx (default: stop at first 2xx per endpoint)}';

    protected $description = 'Probe TopDawg API to discover the real price-push endpoint + request shape';

    /**
     * Curated list of likely endpoint paths. Order from "most likely" → "long shot".
     * Pattern is derived from the known list endpoints: `/SupplierProduct/list`,
     * `/SupplierOrder/list` — so update / save / price variants follow the same
     * `SupplierProduct/<verb>` PascalCase convention.
     */
    private const CANDIDATE_ENDPOINTS = [
        '/SupplierProduct/update',
        '/SupplierProduct/updatePrice',
        '/SupplierProduct/setPrice',
        '/SupplierProduct/save',
        '/SupplierProduct/edit',
        '/SupplierProduct/price',
        '/SupplierProduct/bulkUpdate',
        '/SupplierProduct/bulk-update',
        '/SupplierProduct/update-price',
    ];

    /**
     * @see TopDawgApiService::buildPushPriceBody for shape semantics.
     *
     * Order matters — we try product_code shapes FIRST since the probe already
     * confirmed `POST /SupplierProduct/update` validates `product_code` as
     * required ("The product code field is required."). Falling back to the
     * older sku/tdid shapes after lets us keep them around for sibling
     * endpoints that might key differently.
     */
    private const CANDIDATE_SHAPES = [
        'pc_sku',
        'pc_tdid',
        'pc_array_sku',
        'pc_array_tdid',
        'flat',
        'flat_tdid',
        'items_array',
        'products',
        'data',
        'id_price',
    ];

    /** Most APIs accept POST; PUT/PATCH are less common but worth a single shot. */
    private const CANDIDATE_METHODS = ['POST', 'PUT', 'PATCH'];

    public function handle(TopDawgApiService $api): int
    {
        $sku    = (string) ($this->option('sku') ?? '');
        $priceS = $this->option('price');
        $dryRun = (bool) $this->option('dry-run');

        if ($sku === '') {
            $this->error('Missing --sku. Pass a real SKU from topdawg_products (e.g. --sku=ABC-123).');
            return self::FAILURE;
        }
        if (!$dryRun && ($priceS === null || $priceS === '')) {
            $this->error('Missing --price. Required unless --dry-run.');
            return self::FAILURE;
        }
        $price = (float) ($priceS ?? 0);

        // Resolve TDID from DB unless explicitly overridden — many APIs key updates
        // on the internal listing id rather than the supplier SKU.
        $tdid = $this->option('tdid');
        if ($tdid === null || $tdid === '') {
            $row = TopDawgProduct::where('sku', $sku)->first();
            if ($row) {
                $tdid = (string) ($row->tdid ?? $row->topdawg_listing_id ?? '');
                $this->line("  Resolved TDID from DB: " . ($tdid !== '' ? $tdid : '(none)'));
            } else {
                $this->warn("  SKU '{$sku}' not found in topdawg_products — TDID-based shapes will use the SKU as a fallback.");
            }
        }

        $endpoints = $this->resolveList((string) $this->option('endpoint'), self::CANDIDATE_ENDPOINTS);
        $shapes    = $this->resolveList((string) $this->option('shape'),    self::CANDIDATE_SHAPES);
        $methods   = $this->resolveList(strtoupper((string) $this->option('method')), self::CANDIDATE_METHODS);
        $probeAll  = (bool) $this->option('all');

        $this->info(sprintf(
            "Probing TopDawg price-push: SKU=%s price=%.2f tdid=%s | %d endpoints × %d shapes × %d methods",
            $sku, $price, $tdid !== '' ? $tdid : '(none)', count($endpoints), count($shapes), count($methods),
        ));
        $this->newLine();

        $wins = [];

        foreach ($endpoints as $endpoint) {
            $endpointDidSucceed = false;

            foreach ($methods as $method) {
                foreach ($shapes as $shape) {
                    $body = $api->buildPushPriceBody($sku, $price, $tdid ?: null, $shape);

                    $this->line("→ <fg=cyan>{$method}</> <fg=yellow>{$endpoint}</> shape=<fg=magenta>{$shape}</>");
                    $this->line('  body: ' . json_encode($body));

                    if ($dryRun) {
                        $this->line('  <fg=gray>(dry-run — not sent)</>');
                        $this->newLine();
                        continue;
                    }

                    try {
                        $r = $api->pushPrice($sku, $price, $tdid ?: null, $endpoint, $shape, $method);
                    } catch (\Throwable $e) {
                        $this->error('  exception: ' . $e->getMessage());
                        $this->newLine();
                        continue;
                    }

                    $statusColor = $r['ok'] ? 'green' : ($r['status'] >= 500 ? 'red' : 'yellow');
                    $this->line("  status: <fg={$statusColor}>{$r['status']}</>");
                    $this->line('  response: ' . $this->prettyResponse($r['response']));

                    if ($r['ok']) {
                        $wins[] = compact('endpoint', 'method', 'shape') + ['status' => $r['status']];
                        $endpointDidSucceed = true;
                        $this->line('  <fg=green;options=bold>✓ SUCCESS</>');
                        if (!$probeAll) {
                            $this->newLine();
                            break 2; // stop trying further shapes/methods on this endpoint
                        }
                    }

                    $this->newLine();
                }

                if ($endpointDidSucceed && !$probeAll) {
                    break;
                }
            }
        }

        $this->newLine();
        $this->info('─── SUMMARY ───');
        if (empty($wins)) {
            $this->warn($dryRun
                ? 'Dry-run complete — no requests were sent.'
                : 'No combination returned a 2xx. Check the responses above; ask TopDawg support if every endpoint 404s.');
            return $dryRun ? self::SUCCESS : self::FAILURE;
        }

        foreach ($wins as $w) {
            $this->line(sprintf(
                '  <fg=green>✓</> %s %s  shape=%s  status=%d',
                $w['method'], $w['endpoint'], $w['shape'], $w['status'],
            ));
        }
        $this->line('');
        $this->line('Next step: hard-code the winning combo as the defaults in TopDawgApiService::pushPrice() and remove the others.');

        return self::SUCCESS;
    }

    /** Resolve "single value override OR full default list". */
    private function resolveList(string $opt, array $defaults): array
    {
        return $opt !== '' ? [$opt] : $defaults;
    }

    private function prettyResponse(mixed $r): string
    {
        if (is_array($r) || is_object($r)) {
            $json = json_encode($r, JSON_UNESCAPED_SLASHES);
            return strlen((string) $json) > 800 ? substr((string) $json, 0, 800) . '… (truncated)' : (string) $json;
        }
        $s = (string) $r;
        return strlen($s) > 800 ? substr($s, 0, 800) . '… (truncated)' : $s;
    }
}
