<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\FbaAgeData;

/**
 * Two-phase sync into fba_age_data:
 *
 *  Phase 1 — FBA Inventory REST API (/fba/inventory/v1/summaries)
 *    Pulls ALL SKUs (1 200+) with real-time quantity breakdown.
 *    Every SKU gets a row; age columns default to 0.
 *
 *  Phase 2 — GET_FBA_INVENTORY_PLANNING_DATA report
 *    Enriches matching SKUs with full age buckets, AIS fee projections,
 *    health status, recommended actions, sales velocity, etc.
 *    Amazon only includes SKUs it actively tracks for inventory health
 *    (~48 rows for this account), but those rows contain all age detail.
 *
 * Run:
 *   php artisan fba:fetch-age-data
 *   php artisan fba:fetch-age-data --truncate        # clear table first
 *   php artisan fba:fetch-age-data --skip-api        # skip Phase 1 (REST API)
 *   php artisan fba:fetch-age-data --skip-report     # skip Phase 2 (planning report)
 *   php artisan fba:fetch-age-data --preview         # dry-run, no DB writes
 */
class FetchFbaAgeData extends Command
{
    protected $signature = 'fba:fetch-age-data
        {--truncate     : Truncate fba_age_data before syncing}
        {--skip-api     : Skip Phase 1 REST API fetch}
        {--skip-report  : Skip Phase 2 planning report fetch}
        {--from-cache   : Load Phase 2 from cached TSV (storage/app/fba_fba-inventory-planning-data.tsv) instead of API}
        {--preview      : Dry-run — show counts without writing to DB}';

    protected $description = 'Sync ALL FBA SKUs into fba_age_data (REST API + planning report age data)';

    private const REPORT_TYPE = 'GET_FBA_INVENTORY_PLANNING_DATA';
    private const MAX_WAIT    = 600;
    private const POLL_SECS   = 20;

    public function handle(): int
    {
        $this->header('FBA Age Data Full Sync');

        // When loading from cache with no API phases, skip token entirely
        $needsApi = !$this->option('skip-api') || (!$this->option('skip-report') && !$this->option('from-cache'));

        $token         = null;
        $endpoint      = config('services.amazon_sp.endpoint');
        $marketplaceId = config('services.amazon_sp.marketplace_id');

        if ($needsApi) {
            $token = $this->getAccessToken();
            if (!$token) {
                $this->error('❌  Cannot get access token. Check SPAPI_* env vars.');
                return 1;
            }
            $this->info('✅  Access token obtained.');
        } else {
            $this->info('ℹ️  Skipping token fetch (cache mode, no API calls needed).');
        }

        if ($this->option('truncate') && !$this->option('preview')) {
            FbaAgeData::truncate();
            $this->info('🗑️  Table truncated.');
        }

        // ── Phase 1: REST API — ALL SKUs ──────────────────────────────────────
        if (!$this->option('skip-api')) {
            $this->line('');
            $this->header('Phase 1 — FBA Inventory REST API (all SKUs)', '─');
            $this->runRestApiPhase($token, $endpoint, $marketplaceId);
        }

        // ── Phase 2: Planning report — age / health enrichment ────────────────
        if (!$this->option('skip-report')) {
            $this->line('');
            $this->header('Phase 2 — Planning Report (age buckets + health)', '─');

            if ($this->option('from-cache')) {
                $cachePath = storage_path('app/fba_fba-inventory-planning-data.tsv');
                if (!file_exists($cachePath)) {
                    $this->error("  ❌  Cache file not found: {$cachePath}");
                } else {
                    $this->info("  Loading from cache: {$cachePath}");
                    $this->enrichFromPlanningReport(file_get_contents($cachePath));
                }
            } else {
                $this->runPlanningReportPhase($token, $endpoint, $marketplaceId);
            }
        }

        $total = FbaAgeData::count();
        $this->line('');
        $this->info("════ Done. Total rows in fba_age_data: {$total} ════");
        return 0;
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  PHASE 1  —  FBA Inventory REST API
    // ══════════════════════════════════════════════════════════════════════════

    private function runRestApiPhase(string $token, string $endpoint, string $marketplaceId): void
    {
        $allItems  = [];
        $nextToken = null;
        $page      = 0;

        do {
            $qs = http_build_query([
                'granularityType' => 'Marketplace',
                'granularityId'   => $marketplaceId,
                'details'         => 'true',
            ]);
            $qs .= '&marketplaceIds=' . urlencode($marketplaceId);
            if ($nextToken) {
                $qs .= '&nextToken=' . urlencode($nextToken);
            }

            $res = Http::withHeaders(['x-amz-access-token' => $token])
                ->get("{$endpoint}/fba/inventory/v1/summaries?{$qs}");

            if ($res->failed()) {
                $this->error('  ❌ API call failed (' . $res->status() . '): ' . $res->body());
                return;
            }

            $payload   = $res->json();
            $items     = $payload['payload']['inventorySummaries'] ?? [];
            $allItems  = array_merge($allItems, $items);
            $nextToken = $payload['pagination']['nextToken'] ?? null;
            $page++;

            $this->line("  Page {$page}: " . count($items) . " items  (total so far: " . count($allItems) . ")");

        } while ($nextToken);

        $this->info("  ✅  Total SKUs from REST API: " . count($allItems));

        if ($this->option('preview')) {
            $this->warn('  Preview mode — skipping DB write.');
            return;
        }

        $upserted = 0;
        foreach ($allItems as $item) {
            $sku = trim($item['sellerSku'] ?? '');
            if (!$sku) continue;

            $d = $item['inventoryDetails'] ?? [];

            FbaAgeData::updateOrCreate(['sku' => $sku], [
                'sku'                    => $sku,
                'fnsku'                  => $item['fnSku'] ?? null,
                'asin'                   => $item['asin'] ?? null,
                'product_name'           => isset($item['productName']) && $item['productName'] !== '' ? $item['productName'] : null,
                'condition'              => $item['condition'] ?? null,
                'available'              => (int) ($d['fulfillableQuantity'] ?? 0),
                'inbound_working'        => (int) ($d['inboundWorkingQuantity'] ?? 0),
                'inbound_shipped'        => (int) ($d['inboundShippedQuantity'] ?? 0),
                'inbound_received'       => (int) ($d['inboundReceivingQuantity'] ?? 0),
                'inbound_quantity'       => (int) ($d['inboundWorkingQuantity'] ?? 0)
                                          + (int) ($d['inboundShippedQuantity'] ?? 0)
                                          + (int) ($d['inboundReceivingQuantity'] ?? 0),
                'reserved_quantity'      => (int) ($d['reservedQuantity']['totalReservedQuantity'] ?? 0),
                'unfulfillable_quantity' => (int) ($d['unfulfillableQuantity']['totalUnfulfillableQuantity'] ?? 0),
            ]);

            $upserted++;
        }

        $this->info("  ✅  Upserted {$upserted} SKUs into fba_age_data.");
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  PHASE 2  —  Planning Report (age / health enrichment)
    // ══════════════════════════════════════════════════════════════════════════

    private function runPlanningReportPhase(string $token, string $endpoint, string $marketplaceId): void
    {
        // Create
        $this->line('  Requesting report...');
        $res = Http::withHeaders([
            'x-amz-access-token' => $token,
            'Content-Type'       => 'application/json',
        ])->post("{$endpoint}/reports/2021-06-30/reports", [
            'reportType'     => self::REPORT_TYPE,
            'marketplaceIds' => [$marketplaceId],
        ]);

        if ($res->failed()) {
            $this->error('  ❌ Create report failed (' . $res->status() . '): ' . $res->body());
            return;
        }

        $reportId = $res->json()['reportId'] ?? null;
        $this->info("  Report ID: {$reportId}");

        // Poll
        $waited     = 0;
        $statusData = null;
        while ($waited < self::MAX_WAIT) {
            sleep(self::POLL_SECS);
            $waited += self::POLL_SECS;

            $poll   = Http::withHeaders(['x-amz-access-token' => $token])
                ->get("{$endpoint}/reports/2021-06-30/reports/{$reportId}");
            $statusData = $poll->json();
            $status     = $statusData['processingStatus'] ?? 'UNKNOWN';
            $this->line("  [{$waited}s] status = {$status}");

            if ($status === 'DONE') break;
            if (in_array($status, ['FATAL', 'CANCELLED'])) {
                $this->error("  ❌ Report ended: {$status}");
                return;
            }
        }

        if (($statusData['processingStatus'] ?? '') !== 'DONE') {
            $this->error('  ❌ Timed out.');
            return;
        }

        $docId = $statusData['reportDocumentId'] ?? null;
        if (!$docId) {
            $this->warn('  ⚠️  DONE but no document (no planning data available).');
            return;
        }

        // Download
        $docRes = Http::withHeaders(['x-amz-access-token' => $token])
            ->get("{$endpoint}/reports/2021-06-30/documents/{$docId}");
        if ($docRes->failed()) {
            $this->error('  ❌ Document fetch failed.');
            return;
        }
        $meta = $docRes->json();
        $raw  = Http::timeout(120)->get($meta['url'])->body();
        if (($meta['compressionAlgorithm'] ?? null) === 'GZIP') {
            $raw = gzdecode($raw);
        }

        $this->info('  ✅  Report downloaded.');
        $this->enrichFromPlanningReport($raw);
    }

    private function enrichFromPlanningReport(string $raw): void
    {
        $lines = array_values(array_filter(explode("\n", trim($raw)), fn($l) => trim($l) !== ''));
        if (empty($lines)) { $this->warn('  ⚠️  Empty report.'); return; }

        $headers = str_getcsv(array_shift($lines), "\t");
        $total   = count($lines);
        $this->info("  Rows in planning report: {$total}  |  Columns: " . count($headers));

        if ($this->option('preview')) {
            $this->warn('  Preview mode — skipping DB write.');
            return;
        }

        $enriched = 0;
        $skipped  = 0;

        foreach ($lines as $line) {
            if (!trim($line)) continue;
            $values = str_getcsv($line, "\t");
            if (count($values) < count($headers)) { $skipped++; continue; }

            $r   = array_combine($headers, $values);
            $sku = trim($r['sku'] ?? '');
            if (!$sku) { $skipped++; continue; }

            FbaAgeData::updateOrCreate(['sku' => $sku], $this->mapPlanningRow($r));
            $enriched++;
        }

        $this->info("  ✅  Enriched {$enriched} SKUs with age/health data.");
        if ($skipped) $this->warn("  ⚠️  Skipped {$skipped} malformed rows.");
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  Helpers
    // ══════════════════════════════════════════════════════════════════════════

    private function getAccessToken(): ?string
    {
        try {
            $res = Http::asForm()->post('https://api.amazon.com/auth/o2/token', [
                'grant_type'    => 'refresh_token',
                'refresh_token' => config('services.amazon_sp.refresh_token'),
                'client_id'     => config('services.amazon_sp.client_id'),
                'client_secret' => config('services.amazon_sp.client_secret'),
            ]);
            return $res->ok() ? ($res->json()['access_token'] ?? null) : null;
        } catch (\Throwable $e) {
            Log::error('FbaAgeData token: ' . $e->getMessage());
            return null;
        }
    }

    private function mapPlanningRow(array $r): array
    {
        $int  = fn($v) => is_numeric($v) ? (int) $v : 0;
        $dec  = fn($v) => is_numeric($v) ? (float) $v : null;
        $str  = fn($v) => trim($v) !== '' ? trim($v) : null;
        $date = fn($v) => preg_match('/^\d{4}-\d{2}-\d{2}/', trim($v)) ? trim($v) : null;
        $bool = fn($v) => strtolower(trim($v)) === 'yes' || trim($v) === '1';
        $aint = fn($v) => is_numeric($v) ? (int) $v : null;

        return [
            'snapshot_date'               => $date($r['snapshot-date'] ?? ''),
            'sku'                         => trim($r['sku']),
            'fnsku'                       => $str($r['fnsku'] ?? ''),
            'asin'                        => $str($r['asin'] ?? ''),
            'product_name'                => $str($r['product-name'] ?? ''),
            'condition'                   => $str($r['condition'] ?? ''),
            'marketplace'                 => $str($r['marketplace'] ?? ''),
            'currency'                    => $str($r['currency'] ?? ''),

            'available'                   => $int($r['available'] ?? 0),
            'pending_removal_quantity'    => $int($r['pending-removal-quantity'] ?? 0),
            'inbound_quantity'            => $int($r['inbound-quantity'] ?? 0),
            'inbound_working'             => $int($r['inbound-working'] ?? 0),
            'inbound_shipped'             => $int($r['inbound-shipped'] ?? 0),
            'inbound_received'            => $int($r['inbound-received'] ?? 0),
            'reserved_quantity'           => $int($r['Total Reserved Quantity'] ?? 0),
            'unfulfillable_quantity'      => $int($r['unfulfillable-quantity'] ?? 0),

            'inv_age_0_to_90_days'        => $int($r['inv-age-0-to-90-days'] ?? 0),
            'inv_age_91_to_180_days'      => $int($r['inv-age-91-to-180-days'] ?? 0),
            'inv_age_181_to_270_days'     => $int($r['inv-age-181-to-270-days'] ?? 0),
            'inv_age_271_to_365_days'     => $int($r['inv-age-271-to-365-days'] ?? 0),
            'inv_age_366_to_455_days'     => $int($r['inv-age-366-to-455-days'] ?? 0),
            'inv_age_456_plus_days'       => $int($r['inv-age-456-plus-days'] ?? 0),
            'inv_age_0_to_30_days'        => $int($r['inv-age-0-to-30-days'] ?? 0),
            'inv_age_31_to_60_days'       => $int($r['inv-age-31-to-60-days'] ?? 0),
            'inv_age_61_to_90_days'       => $int($r['inv-age-61-to-90-days'] ?? 0),
            'inv_age_181_to_330_days'     => $int($r['inv-age-181-to-330-days'] ?? 0),
            'inv_age_331_to_365_days'     => $int($r['inv-age-331-to-365-days'] ?? 0),

            'ais_qty_181_210'  => $aint($r['quantity-to-be-charged-ais-181-210-days'] ?? ''),
            'ais_est_181_210'  => $dec($r['estimated-ais-181-210-days'] ?? ''),
            'ais_qty_211_240'  => $aint($r['quantity-to-be-charged-ais-211-240-days'] ?? ''),
            'ais_est_211_240'  => $dec($r['estimated-ais-211-240-days'] ?? ''),
            'ais_qty_241_270'  => $aint($r['quantity-to-be-charged-ais-241-270-days'] ?? ''),
            'ais_est_241_270'  => $dec($r['estimated-ais-241-270-days'] ?? ''),
            'ais_qty_271_300'  => $aint($r['quantity-to-be-charged-ais-271-300-days'] ?? ''),
            'ais_est_271_300'  => $dec($r['estimated-ais-271-300-days'] ?? ''),
            'ais_qty_301_330'  => $aint($r['quantity-to-be-charged-ais-301-330-days'] ?? ''),
            'ais_est_301_330'  => $dec($r['estimated-ais-301-330-days'] ?? ''),
            'ais_qty_331_365'  => $aint($r['quantity-to-be-charged-ais-331-365-days'] ?? ''),
            'ais_est_331_365'  => $dec($r['estimated-ais-331-365-days'] ?? ''),
            'ais_qty_366_455'  => $aint($r['quantity-to-be-charged-ais-366-455-days'] ?? ''),
            'ais_est_366_455'  => $dec($r['estimated-ais-366-455-days'] ?? ''),
            'ais_qty_456_plus' => $aint($r['quantity-to-be-charged-ais-456-plus-days'] ?? ''),
            'ais_est_456_plus' => $dec($r['estimated-ais-456-plus-days'] ?? ''),

            'units_shipped_t7'            => $int($r['units-shipped-t7'] ?? 0),
            'units_shipped_t30'           => $int($r['units-shipped-t30'] ?? 0),
            'units_shipped_t60'           => $int($r['units-shipped-t60'] ?? 0),
            'units_shipped_t90'           => $int($r['units-shipped-t90'] ?? 0),
            'sales_shipped_last_7_days'   => $dec($r['sales-shipped-last-7-days'] ?? ''),
            'sales_shipped_last_30_days'  => $dec($r['sales-shipped-last-30-days'] ?? ''),
            'sales_shipped_last_60_days'  => $dec($r['sales-shipped-last-60-days'] ?? ''),
            'sales_shipped_last_90_days'  => $dec($r['sales-shipped-last-90-days'] ?? ''),

            'your_price'                     => $dec($r['your-price'] ?? ''),
            'sales_price'                    => $dec($r['sales-price'] ?? ''),
            'lowest_price_new_plus_shipping' => $dec($r['lowest-price-new-plus-shipping'] ?? ''),
            'lowest_price_used'              => $dec($r['lowest-price-used'] ?? ''),
            'featuredoffer_price'            => $dec($r['featuredoffer-price'] ?? ''),

            'health_status'                  => $str($r['fba-inventory-level-health-status'] ?? ''),
            'alert'                          => $str($r['alert'] ?? ''),
            'recommended_action'             => $str($r['recommended-action'] ?? ''),
            'recommended_removal_quantity'   => $int($r['recommended-removal-quantity'] ?? 0),
            'recommended_sales_price'        => $dec($r['recommended-sales-price'] ?? ''),
            'recommended_sale_duration_days' => $aint($r['recommended-sale-duration-days'] ?? ''),
            'estimated_cost_savings'         => $dec($r['estimated-cost-savings-of-recommended-actions'] ?? ''),
            'no_sale_last_6_months'          => $bool($r['no-sale-last-6-months'] ?? ''),

            'sell_through'              => $dec($r['sell-through'] ?? ''),
            'days_of_supply'            => $aint($r['days-of-supply'] ?? ''),
            'total_days_of_supply'      => $aint($r['Total Days of Supply (including units from open shipments)'] ?? ''),
            'estimated_excess_quantity' => $aint($r['estimated-excess-quantity'] ?? ''),
            'weeks_of_cover_t30'        => $dec($r['weeks-of-cover-t30'] ?? ''),
            'weeks_of_cover_t90'        => $dec($r['weeks-of-cover-t90'] ?? ''),
            'historical_days_of_supply' => $dec($r['historical-days-of-supply'] ?? ''),
            'short_term_days_of_supply' => $dec($r['Short term historical days of supply'] ?? ''),
            'long_term_days_of_supply'  => $dec($r['Long term historical days of supply'] ?? ''),
            'fba_minimum_inventory_level' => $aint($r['fba-minimum-inventory-level'] ?? ''),
            'inventory_age_snapshot_date' => $date($r['Inventory age snapshot date'] ?? ''),

            'storage_type'             => $str($r['storage-type'] ?? ''),
            'storage_volume'           => $dec($r['storage-volume'] ?? ''),
            'item_volume'              => $dec($r['item-volume'] ?? ''),
            'volume_unit_measurement'  => $str($r['volume-unit-measurement'] ?? ''),

            'estimated_storage_cost_next_month' => $dec($r['estimated-storage-cost-next-month'] ?? ''),

            'sales_rank'    => $aint($r['sales-rank'] ?? ''),
            'product_group' => $str($r['product-group'] ?? ''),
        ];
    }

    private function header(string $title, string $char = '═'): void
    {
        $line = str_repeat($char, 60);
        $this->line('');
        $this->info($line);
        $this->info("  {$title}");
        $this->info($line);
    }
}
